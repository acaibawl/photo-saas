<?php

namespace App\Application\Guardian\Auth;

use App\Domain\Guardian\Exceptions\GuardianEmailAlreadyExistsException;
use App\Domain\Guardian\Exceptions\GuardianInvitationAlreadyUsedException;
use App\Domain\Guardian\Exceptions\GuardianInvitationInvalidOrExpiredException;
use App\Domain\Guardian\Exceptions\GuardianLoginRateLimitedException;
use App\Domain\Guardian\Exceptions\GuardianRefreshTokenReuseDetectedException;
use App\Domain\Guardian\Exceptions\InvalidGuardianCredentialsException;
use App\Domain\Guardian\Exceptions\InvalidGuardianRefreshTokenException;
use App\Domain\Shared\EmailAddress;
use App\Domain\Shared\SecureToken;
use App\Models\ChildInvitation;
use App\Models\Guardian;
use App\Models\GuardianChild;
use App\Models\GuardianRefreshToken;
use App\Notifications\GuardianEmailVerificationNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

final class GuardianAuthService
{
    private const LOGIN_LIMIT = 6;

    private const IP_LIMIT = 30;

    private const LOGIN_TTL_MINUTES = 15;

    public function __construct(private readonly GuardianTokenService $tokenService) {}

    public function previewInvitation(string $rawToken): array
    {
        $invitation = $this->findInvitationByRawToken($rawToken);

        if ($invitation === null || ! $this->isInvitationAvailable($invitation)) {
            throw new GuardianInvitationInvalidOrExpiredException;
        }

        return [
            'kindergarten_name' => $invitation->kindergarten->name,
            'child_name' => $invitation->child->name,
            'class_name' => $invitation->child->childClass->name ?? '',
            'label' => $invitation->label,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ];
    }

    public function acceptInvitation(
        string $rawToken,
        string $name,
        string $email,
        string $password,
        string $ipAddress,
        ?string $userAgent = null,
    ): array {
        $newGuardian = null;

        $result = DB::transaction(function () use ($rawToken, $name, $email, $password, $ipAddress, $userAgent, &$newGuardian): array {
            $normalizedEmail = EmailAddress::fromString($email)->normalized();

            /** @var ChildInvitation|null $invitation */
            $invitation = ChildInvitation::query()
                ->with(['kindergarten', 'child.childClass'])
                ->where('token_hash', SecureToken::hashOf($rawToken))
                ->lockForUpdate()
                ->first();

            if (! $invitation instanceof ChildInvitation) {
                throw new GuardianInvitationInvalidOrExpiredException;
            }

            if ($invitation->used_at !== null) {
                throw new GuardianInvitationAlreadyUsedException;
            }

            if (! $this->isInvitationAvailable($invitation)) {
                throw new GuardianInvitationInvalidOrExpiredException;
            }

            $guardian = Guardian::query()->where('email', $normalizedEmail)->first();

            if ($guardian === null) {
                $guardian = Guardian::create([
                    'name' => trim($name),
                    'email' => $normalizedEmail,
                    'password_hash' => Hash::make($password),
                ]);
                $newGuardian = $guardian;
            } elseif (! Hash::check($password, $guardian->password_hash)) {
                throw new GuardianEmailAlreadyExistsException;
            }

            $activeLinkExists = GuardianChild::query()
                ->where('guardian_id', $guardian->id)
                ->where('child_id', $invitation->child_id)
                ->whereNull('unlinked_at')
                ->exists();

            if (! $activeLinkExists) {
                GuardianChild::create([
                    'guardian_id' => $guardian->id,
                    'child_id' => $invitation->child_id,
                    'kindergarten_id' => $invitation->kindergarten_id,
                    'label' => $invitation->label,
                    'linked_at' => now(),
                ]);
            }

            $invitation->forceFill([
                'used_at' => now(),
                'used_by_guardian_id' => $guardian->id,
            ])->save();

            $tokens = $this->tokenService->issueTokensForGuardian($guardian, $ipAddress, $userAgent);

            return [
                'access_token' => $tokens['access_token'],
                'token_type' => $tokens['token_type'],
                'expires_in' => $tokens['expires_in'],
                'guardian' => [
                    'id' => $guardian->id,
                    'name' => $guardian->name,
                    'email' => $guardian->email,
                ],
                'linked_child' => [
                    'id' => $invitation->child_id,
                ],
                'refresh_token' => $tokens['refresh_token'],
            ];
        });

        if ($newGuardian instanceof Guardian) {
            $this->sendEmailVerificationNotification($newGuardian);
        }

        return $result;
    }

    public function login(string $email, string $password, string $ipAddress, ?string $userAgent = null): array
    {
        $normalizedEmail = $this->normalizeEmail($email);

        if ($this->isLoginRateLimited($normalizedEmail, $ipAddress)) {
            throw new GuardianLoginRateLimitedException;
        }

        $guardian = Guardian::query()->where('email', $normalizedEmail)->first();

        if ($guardian === null || ! Hash::check($password, $guardian->password_hash)) {
            $this->recordFailedLogin($normalizedEmail, $ipAddress);

            throw new InvalidGuardianCredentialsException;
        }

        $this->clearLoginState($normalizedEmail, $ipAddress);

        $tokens = $this->tokenService->issueTokensForGuardian($guardian, $ipAddress, $userAgent);

        return [
            'access_token' => $tokens['access_token'],
            'token_type' => $tokens['token_type'],
            'expires_in' => $tokens['expires_in'],
            'guardian' => [
                'id' => $guardian->id,
                'name' => $guardian->name,
                'email' => $guardian->email,
            ],
            'refresh_token' => $tokens['refresh_token'],
        ];
    }

    public function refresh(string $refreshTokenValue, string $ipAddress, ?string $userAgent = null): array
    {
        $plainToken = trim($refreshTokenValue);

        if ($plainToken === '') {
            throw new InvalidGuardianRefreshTokenException;
        }

        $tokenHash = hash('sha256', $plainToken);
        $reuseDetectedFamilyId = null;

        $result = DB::transaction(function () use ($tokenHash, $ipAddress, $userAgent, &$reuseDetectedFamilyId): ?array {
            /** @var GuardianRefreshToken|null $refreshToken */
            $refreshToken = GuardianRefreshToken::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (! $refreshToken instanceof GuardianRefreshToken) {
                throw new InvalidGuardianRefreshTokenException;
            }

            if ($refreshToken->revoked_at !== null) {
                $reuseDetectedFamilyId = $refreshToken->family_id;

                return null;
            }

            if ($refreshToken->expires_at->isPast() || $refreshToken->family_expires_at->isPast()) {
                throw new InvalidGuardianRefreshTokenException;
            }

            $refreshToken->forceFill(['revoked_at' => now()])->save();

            $guardian = Guardian::query()->findOrFail($refreshToken->guardian_id);
            $tokens = $this->tokenService->issueTokensForGuardian($guardian, $ipAddress, $userAgent, $refreshToken->family_id);

            return [
                'access_token' => $tokens['access_token'],
                'token_type' => $tokens['token_type'],
                'expires_in' => $tokens['expires_in'],
                'refresh_token' => $tokens['refresh_token'],
            ];
        });

        if ($result === null) {
            if ($reuseDetectedFamilyId === null) {
                throw new \LogicException('Refresh token family ID was not captured during reuse detection.');
            }

            $this->tokenService->revokeRefreshTokenFamily($reuseDetectedFamilyId);

            throw new GuardianRefreshTokenReuseDetectedException;
        }

        return $result;
    }

    public function sendEmailVerificationNotification(Guardian $guardian): bool
    {
        $guardian->notify(new GuardianEmailVerificationNotification);

        return true;
    }

    public function verifyEmail(string $id, string $hash): ?string
    {
        $guardian = Guardian::query()->findOrFail($id);

        if (! hash_equals((string) sha1($guardian->email), $hash)) {
            throw new \InvalidArgumentException('Email hash mismatch');
        }

        if (! $guardian->hasVerifiedEmail()) {
            $guardian->markEmailAsVerified();
        }

        return $guardian->fresh()?->email_verified_at?->toIso8601String();
    }

    private function isInvitationAvailable(ChildInvitation $invitation): bool
    {
        return $invitation->used_at === null
            && $invitation->revoked_at === null
            && $invitation->expires_at->isFuture();
    }

    private function findInvitationByRawToken(string $rawToken): ?ChildInvitation
    {
        return ChildInvitation::query()
            ->with(['kindergarten', 'child.childClass'])
            ->where('token_hash', SecureToken::hashOf($rawToken))
            ->first();
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

        if ($emailFailures >= self::LOGIN_LIMIT) {
            $delaySeconds = min(60, 2 ** ($emailFailures - 5));
            Cache::put($emailKey.':blocked', now()->addSeconds($delaySeconds)->getTimestamp(), now()->addSeconds($delaySeconds));
        }

        if ($ipFailures >= self::LOGIN_LIMIT) {
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
        return 'guardian-auth:login:email:'.$email;
    }

    private function ipLoginCacheKey(string $ipAddress): string
    {
        return 'guardian-auth:login:ip:'.$ipAddress;
    }
}
