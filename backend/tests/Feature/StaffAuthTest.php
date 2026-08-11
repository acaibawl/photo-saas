<?php

namespace Tests\Feature;

use App\Domain\Shared\SecureToken;
use App\Domain\Staff\StaffRole;
use App\Models\Kindergarten;
use App\Models\KindergartenStaff;
use App\Models\StaffRefreshToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StaffAuthTest extends TestCase
{
    use RefreshDatabase;

    private KindergartenStaff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $kindergarten = Kindergarten::create([
            'name' => 'みらい幼稚園',
            'slug' => 'mirai',
        ]);

        $this->staff = KindergartenStaff::create([
            'kindergarten_id' => $kindergarten->id,
            'name' => '山田太郎',
            'email' => 'staff@example.com',
            'email_normalized' => 'staff@example.com',
            'password_hash' => Hash::make('password-123'),
            'role' => StaffRole::Owner,
        ]);
    }

    public function test_staff_can_login_and_fetch_profile(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/staff/auth/login', [
            'email' => 'staff@example.com',
            'password' => 'password-123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'staff' => ['id', 'kindergarten_id', 'role'],
            ])
            ->assertJsonPath('staff.email', 'staff@example.com');

        $this->assertDatabaseHas('staff_refresh_tokens', [
            'kindergarten_staff_id' => $this->staff->id,
        ]);

        $accessToken = $response->json('access_token');

        $profileResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$accessToken,
        ])->getJson('/staff/auth/me');

        $profileResponse->assertOk()
            ->assertJsonPath('email', 'staff@example.com')
            ->assertJsonPath('role', 'owner');
    }

    public function test_login_returns_unauthorized_for_invalid_credentials(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/staff/auth/login', [
            'email' => 'staff@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('code', 'STAFF_AUTH_INVALID_CREDENTIALS');
    }

    public function test_refresh_and_logout_revoke_tokens(): void
    {
        $firstToken = SecureToken::generate();
        $secondToken = SecureToken::generate();

        $firstRefreshToken = StaffRefreshToken::create([
            'kindergarten_staff_id' => $this->staff->id,
            'token_hash' => $firstToken->hash(),
            'family_id' => 'family-1',
            'family_expires_at' => now()->addDays(14),
            'expires_at' => now()->addDays(14),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $secondRefreshToken = StaffRefreshToken::create([
            'kindergarten_staff_id' => $this->staff->id,
            'token_hash' => $secondToken->hash(),
            'family_id' => 'family-2',
            'family_expires_at' => now()->addDays(14),
            'expires_at' => now()->addDays(14),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Cookie' => 'refresh_token='.rawurlencode($firstToken->plainText()),
        ])->postJson('/staff/auth/refresh');

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);

        $firstRefreshToken->refresh();
        $this->assertNotNull($firstRefreshToken->revoked_at);

        $setCookieHeader = $response->headers->get('set-cookie');
        $this->assertIsString($setCookieHeader);
        preg_match('/refresh_token=([^;]+)/', $setCookieHeader, $matches);
        $this->assertNotEmpty($matches[1] ?? []);

        $rotatedRefreshToken = urldecode($matches[1]);

        $logoutResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$response->json('access_token'),
            'Cookie' => 'refresh_token='.rawurlencode($rotatedRefreshToken),
        ])->postJson('/staff/auth/logout', ['all_sessions' => false]);

        $logoutResponse->assertOk()
            ->assertJsonPath('revoked_count', 1);

        $this->assertDatabaseHas('staff_refresh_tokens', [
            'id' => $secondRefreshToken->id,
            'revoked_at' => null,
        ]);
    }

    public function test_single_session_logout_requires_a_refresh_token(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($this->staff),
        ])->postJson('/staff/auth/logout', ['all_sessions' => false]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.refresh_token.0', 'validation.required');
    }
}
