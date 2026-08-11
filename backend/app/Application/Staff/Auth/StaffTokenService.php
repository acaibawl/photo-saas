<?php

namespace App\Application\Staff\Auth;

use App\Models\KindergartenStaff;
use App\Models\StaffRefreshToken;
use Illuminate\Support\Str;
use Tymon\JWTAuth\JWT;

final class StaffTokenService
{
    public function __construct(private readonly JWT $jwt) {}

    public function issueTokensForStaff(
        KindergartenStaff $staff,
        string $ipAddress,
        ?string $userAgent = null,
        ?string $familyId = null,
    ): array {
        $accessToken = $this->jwt->fromUser($staff);
        $plainRefreshToken = $this->issueRefreshToken($staff, $ipAddress, $userAgent, $familyId);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
            'refresh_token' => $plainRefreshToken,
        ];
    }

    public function revokeRefreshTokenFamily(string $familyId): void
    {
        StaffRefreshToken::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeAllActiveRefreshTokensForStaff(string $staffId): int
    {
        return StaffRefreshToken::query()
            ->where('kindergarten_staff_id', $staffId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function issueRefreshToken(
        KindergartenStaff $staff,
        string $ipAddress,
        ?string $userAgent,
        ?string $familyId = null,
    ): string {
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
}
