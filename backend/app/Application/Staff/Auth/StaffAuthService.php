<?php

namespace App\Application\Staff\Auth;

use App\Domain\Staff\Exceptions\InvalidStaffCredentialsException;
use App\Domain\Staff\Exceptions\InvalidStaffRefreshTokenException;
use App\Domain\Staff\Exceptions\StaffRefreshTokenReuseDetectedException;
use App\Models\KindergartenStaff;
use App\Models\StaffRefreshToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

final class StaffAuthService
{
    private const LOGIN_LIMIT = 5;

    private const IP_LIMIT = 30;

    private const LOGIN_TTL_MINUTES = 1;

    public function login(string $email, string $password, string $ipAddress, ?string $userAgent = null): array
    {
        $normalizedEmail = $this->normalizeEmail($email);

        if ($this->isLoginRateLimited($normalizedEmail, $ipAddress)) {
            throw new \RuntimeException('Too many login attempts', 429);
        }

        $staff = KindergartenStaff::query()
            ->where('email_normalized', $normalizedEmail)
            ->first();

        if ($staff === null || ! Hash::check($password, $staff->password_hash)) {
            $this->recordFailedLogin($normalizedEmail, $ipAddress);

            throw new InvalidStaffCredentialsException;
        }

        $this->clearLoginState($normalizedEmail, $ipAddress);

        $staff->forceFill(['last_login_at' => now()])->save();

        $accessToken = JWTAuth::fromUser($staff);
        $plainRefreshToken = $this->issueRefreshToken($staff, $ipAddress, $userAgent);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
            'staff' => [
                'id' => $staff->id,
                'kindergarten_id' => $staff->kindergarten_id,
                'name' => $staff->name,
                'email' => $staff->email,
                'role' => $staff->role->value,
            ],
            'refresh_token' => $plainRefreshToken,
        ];
    }

    public function refresh(string $refreshTokenValue, string $ipAddress, ?string $userAgent = null): array
    {
        $plainToken = trim($refreshTokenValue);

        if ($plainToken === '') {
            throw new InvalidStaffRefreshTokenException;
        }

        $tokenHash = hash('sha256', $plainToken);

        return DB::transaction(function () use ($tokenHash, $ipAddress, $userAgent): array {
            $refreshToken = StaffRefreshToken::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($refreshToken === null) {
                throw new InvalidStaffRefreshTokenException;
            }

            if ($refreshToken->revoked_at !== null) {
                $this->revokeRefreshTokenFamily($refreshToken->family_id);

                throw new StaffRefreshTokenReuseDetectedException;
            }

            if ($refreshToken->expires_at->isPast() || $refreshToken->family_expires_at->isPast()) {
                throw new InvalidStaffRefreshTokenException;
            }

            $refreshToken->forceFill(['revoked_at' => now()])->save();

            $staff = KindergartenStaff::query()->findOrFail($refreshToken->kindergarten_staff_id);
            $newPlainToken = $this->issueRefreshToken($staff, $ipAddress, $userAgent, $refreshToken->family_id);
            $accessToken = JWTAuth::fromUser($staff);

            return [
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => (int) config('jwt.ttl', 60) * 60,
                'refresh_token' => $newPlainToken,
            ];
        });
    }

    public function logout(KindergartenStaff $staff, bool $allSessions, ?string $refreshTokenValue = null): int
    {
        $query = StaffRefreshToken::query()
            ->where('kindergarten_staff_id', $staff->id)
            ->whereNull('revoked_at');

        if (! $allSessions) {
            if ($refreshTokenValue !== null && trim($refreshTokenValue) !== '') {
                $query->where('token_hash', hash('sha256', trim($refreshTokenValue)));
            }
        }

        return $query->update(['revoked_at' => now()]);
    }

    private function issueRefreshToken(KindergartenStaff $staff, string $ipAddress, ?string $userAgent, ?string $familyId = null): string
    {
        $plainToken = bin2hex(random_bytes(32));

        StaffRefreshToken::create([
            'kindergarten_staff_id' => $staff->id,
            'token_hash' => hash('sha256', $plainToken),
            'family_id' => $familyId ?? (string) Str::uuid(),
            'family_expires_at' => now()->addDays(14),
            'expires_at' => now()->addDays(14),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        return $plainToken;
    }

    private function revokeRefreshTokenFamily(string $familyId): void
    {
        StaffRefreshToken::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function isLoginRateLimited(string $email, string $ipAddress): bool
    {
        $emailKey = $this->emailLoginCacheKey($email);
        $ipKey = $this->ipLoginCacheKey($ipAddress);

        $emailBlockedUntil = Cache::get($emailKey.':blocked');
        if ($emailBlockedUntil !== null && now()->lt(Carbon::createFromTimestamp($emailBlockedUntil))) {
            return true;
        }

        $ipBlockedUntil = Cache::get($ipKey.':blocked');
        if ($ipBlockedUntil !== null && now()->lt(Carbon::createFromTimestamp($ipBlockedUntil))) {
            return true;
        }

        $emailFailures = RateLimiter::attempts($emailKey);
        $ipFailures = RateLimiter::attempts($ipKey);

        return $emailFailures >= self::LOGIN_LIMIT || $ipFailures >= self::IP_LIMIT;
    }

    private function recordFailedLogin(string $email, string $ipAddress): void
    {
        $emailKey = $this->emailLoginCacheKey($email);
        $ipKey = $this->ipLoginCacheKey($ipAddress);

        $emailFailures = RateLimiter::increment($emailKey, self::LOGIN_TTL_MINUTES * 60);
        $ipFailures = RateLimiter::increment($ipKey, self::LOGIN_TTL_MINUTES * 60);

        if ($emailFailures >= 6) {
            $delaySeconds = min(60, 2 ** ($emailFailures - 5));
            Cache::put($emailKey.':blocked', now()->addSeconds($delaySeconds)->getTimestamp(), now()->addSeconds($delaySeconds));
        }

        if ($ipFailures >= 6) {
            $delaySeconds = min(60, 2 ** ($ipFailures - 5));
            Cache::put($ipKey.':blocked', now()->addSeconds($delaySeconds)->getTimestamp(), now()->addSeconds($delaySeconds));
        }
    }

    private function clearLoginState(string $email, string $ipAddress): void
    {
        RateLimiter::clear($this->emailLoginCacheKey($email));
        RateLimiter::clear($this->ipLoginCacheKey($ipAddress));
        Cache::forget($this->emailLoginCacheKey($email).':blocked');
        Cache::forget($this->ipLoginCacheKey($ipAddress).':blocked');
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function emailLoginCacheKey(string $email): string
    {
        return 'staff-auth:login:email:'.$email;
    }

    private function ipLoginCacheKey(string $ipAddress): string
    {
        return 'staff-auth:login:ip:'.$ipAddress;
    }
}
