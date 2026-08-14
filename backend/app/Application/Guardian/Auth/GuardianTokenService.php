<?php

namespace App\Application\Guardian\Auth;

use App\Models\Guardian;
use App\Models\GuardianRefreshToken;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Tymon\JWTAuth\JWT;

final class GuardianTokenService
{
    public function __construct(private readonly JWT $jwt) {}

    public function issueTokensForGuardian(
        Guardian $guardian,
        string $ipAddress,
        ?string $userAgent = null,
        ?string $familyId = null,
    ): array {
        $accessToken = $this->jwt->fromUser($guardian);
        $plainRefreshToken = $this->issueRefreshToken($guardian, $ipAddress, $userAgent, $familyId);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
            'refresh_token' => $plainRefreshToken,
        ];
    }

    public function revokeRefreshTokenFamily(string $familyId): void
    {
        GuardianRefreshToken::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeAllActiveRefreshTokensForGuardian(string $guardianId): int
    {
        return GuardianRefreshToken::query()
            ->where('guardian_id', $guardianId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function issueRefreshToken(
        Guardian $guardian,
        string $ipAddress,
        ?string $userAgent,
        ?string $familyId = null,
    ): string {
        $plainToken = bin2hex(random_bytes(32));
        $resolvedFamilyId = $familyId ?? (string) Str::uuid();
        $familyExpiresAt = $this->resolveFamilyExpiresAt($resolvedFamilyId, $familyId !== null);

        GuardianRefreshToken::create([
            'guardian_id' => $guardian->id,
            'token_hash' => hash('sha256', $plainToken),
            'family_id' => $resolvedFamilyId,
            'family_expires_at' => $familyExpiresAt,
            'expires_at' => now()->addDays(14),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        return $plainToken;
    }

    private function resolveFamilyExpiresAt(string $familyId, bool $isRotation): CarbonInterface
    {
        if (! $isRotation) {
            return now()->addDays(14);
        }

        $existingFamilyToken = GuardianRefreshToken::query()
            ->where('family_id', $familyId)
            ->orderBy('created_at')
            ->first();

        if ($existingFamilyToken instanceof GuardianRefreshToken) {
            return $existingFamilyToken->family_expires_at;
        }

        return now()->addDays(14);
    }
}
