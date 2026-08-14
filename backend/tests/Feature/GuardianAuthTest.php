<?php

namespace Tests\Feature;

use App\Domain\Shared\SecureToken;
use App\Models\Child;
use App\Models\ChildClass;
use App\Models\ChildInvitation;
use App\Models\Guardian;
use App\Models\GuardianChild;
use App\Models\GuardianRefreshToken;
use App\Models\Kindergarten;
use App\Models\KindergartenStaff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class GuardianAuthTest extends TestCase
{
    use RefreshDatabase;

    private Kindergarten $kindergarten;

    private Child $child;

    private KindergartenStaff $staff;

    private Guardian $guardian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kindergarten = Kindergarten::create([
            'name' => 'ひかり保育園',
            'slug' => 'hikari',
        ]);

        $this->staff = KindergartenStaff::create([
            'kindergarten_id' => $this->kindergarten->id,
            'name' => '園長',
            'email' => 'owner@example.com',
            'email_normalized' => 'owner@example.com',
            'password_hash' => Hash::make('password-123'),
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $childClass = ChildClass::create([
            'kindergarten_id' => $this->kindergarten->id,
            'name' => 'ひよこ組',
        ]);

        $this->child = Child::create([
            'child_class_id' => $childClass->id,
            'name' => '山田花子',
            'status' => 'enrolled',
        ]);

        $this->guardian = Guardian::create([
            'name' => '保護者太郎',
            'email' => 'guardian@example.com',
            'password_hash' => Hash::make('password-123'),
            'email_verified_at' => now(),
        ]);
    }

    public function test_guardian_can_preview_and_accept_public_child_invitation(): void
    {
        $token = SecureToken::generate();

        $invitation = ChildInvitation::create([
            'kindergarten_id' => $this->kindergarten->id,
            'child_id' => $this->child->id,
            'token_hash' => $token->hash(),
            'label' => '父用',
            'expires_at' => now()->addDays(7),
            'created_by_staff_id' => $this->staff->id,
        ]);

        $previewResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson('/public/invitations/'.$token->plainText());

        $previewResponse->assertOk()
            ->assertJsonPath('kindergarten_name', 'ひかり保育園')
            ->assertJsonPath('child_name', '山田花子')
            ->assertJsonPath('label', '父用');

        $acceptResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/public/invitations/'.$token->plainText().'/accept', [
                'name' => '新規保護者',
                'email' => 'new-guardian@example.com',
                'password' => 'password-123',
            ]);

        $acceptResponse->assertOk()
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'guardian' => ['id', 'name', 'email'],
                'linked_child' => ['id'],
            ])
            ->assertJsonPath('guardian.email', 'new-guardian@example.com');

        $this->assertDatabaseHas('guardian_child', [
            'guardian_id' => $acceptResponse->json('guardian.id'),
            'child_id' => $this->child->id,
            'kindergarten_id' => $this->kindergarten->id,
        ]);

        $invitation->refresh();
        $this->assertNotNull($invitation->used_at);
    }

    public function test_guardian_invitation_accept_rejects_case_variant_existing_email(): void
    {
        $token = SecureToken::generate();

        ChildInvitation::create([
            'kindergarten_id' => $this->kindergarten->id,
            'child_id' => $this->child->id,
            'token_hash' => $token->hash(),
            'label' => '母用',
            'expires_at' => now()->addDays(7),
            'created_by_staff_id' => $this->staff->id,
        ]);

        $acceptResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/public/invitations/'.$token->plainText().'/accept', [
                'name' => '別名義',
                'email' => 'Guardian@Example.com',
                'password' => 'different-password',
            ]);

        $acceptResponse->assertStatus(409)
            ->assertJsonPath('code', 'GUARDIAN_EMAIL_ALREADY_EXISTS');

        $this->assertDatabaseCount('guardians', 1);
    }

    public function test_existing_guardian_must_authenticate_before_accepting_invitation(): void
    {
        $token = SecureToken::generate();

        $invitation = ChildInvitation::create([
            'kindergarten_id' => $this->kindergarten->id,
            'child_id' => $this->child->id,
            'token_hash' => $token->hash(),
            'label' => '父用',
            'expires_at' => now()->addDays(7),
            'created_by_staff_id' => $this->staff->id,
        ]);

        $failedResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/public/invitations/'.$token->plainText().'/accept', [
                'name' => '保護者太郎',
                'email' => 'guardian@example.com',
                'password' => 'wrong-password',
            ]);

        $failedResponse->assertStatus(409)
            ->assertJsonPath('code', 'GUARDIAN_EMAIL_ALREADY_EXISTS');

        $this->assertNull($invitation->fresh()->used_at);

        $acceptedResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/public/invitations/'.$token->plainText().'/accept', [
                'name' => '保護者太郎',
                'email' => 'guardian@example.com',
                'password' => 'password-123',
            ]);

        $acceptedResponse->assertOk()
            ->assertJsonPath('guardian.id', $this->guardian->id)
            ->assertJsonPath('guardian.email', 'guardian@example.com');

        $this->assertDatabaseCount('guardians', 1);
        $this->assertDatabaseHas('guardian_child', [
            'guardian_id' => $this->guardian->id,
            'child_id' => $this->child->id,
            'kindergarten_id' => $this->kindergarten->id,
        ]);
    }

    public function test_guardian_login_and_refresh_work_with_token_rotation(): void
    {
        $loginResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/guardian/auth/login', [
                'email' => 'guardian@example.com',
                'password' => 'password-123',
            ]);

        $loginResponse->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'guardian' => ['id']])
            ->assertJsonPath('guardian.email', 'guardian@example.com');

        $firstRefreshToken = $this->extractRefreshToken($loginResponse);

        $refreshResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Cookie' => 'refresh_token='.rawurlencode($firstRefreshToken),
        ])->postJson('/guardian/auth/refresh');

        $refreshResponse->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);

        $rotatedRefreshToken = $this->extractRefreshToken($refreshResponse);

        $reuseResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Cookie' => 'refresh_token='.rawurlencode($firstRefreshToken),
        ])->postJson('/guardian/auth/refresh');

        $reuseResponse->assertStatus(401)
            ->assertJsonPath('code', 'GUARDIAN_AUTH_REFRESH_REUSE_DETECTED');

        $rotatedTokenResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Cookie' => 'refresh_token='.rawurlencode($rotatedRefreshToken),
        ])->postJson('/guardian/auth/refresh');

        $rotatedTokenResponse->assertStatus(401)
            ->assertJsonPath('code', 'GUARDIAN_AUTH_REFRESH_REUSE_DETECTED');
    }

    public function test_guardian_logout_revokes_all_active_refresh_tokens(): void
    {
        $loginResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/guardian/auth/login', [
                'email' => 'guardian@example.com',
                'password' => 'password-123',
            ]);

        $loginResponse->assertOk();

        $extraRefreshToken = SecureToken::generate();
        GuardianRefreshToken::create([
            'guardian_id' => $this->guardian->id,
            'token_hash' => $extraRefreshToken->hash(),
            'family_id' => 'family-guardian-extra',
            'family_expires_at' => now()->addDays(14),
            'expires_at' => now()->addDays(14),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $logoutResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$loginResponse->json('access_token'),
        ])->postJson('/guardian/auth/logout');

        $logoutResponse->assertOk()
            ->assertJsonPath('revoked_count', 2);

        $this->assertSame(0, GuardianRefreshToken::query()
            ->where('guardian_id', $this->guardian->id)
            ->whereNull('revoked_at')
            ->count());
    }

    public function test_guardian_login_rate_limit_preserves_failures_beyond_one_minute(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $this->withHeaders(['Accept' => 'application/json'])
                ->postJson('/guardian/auth/login', [
                    'email' => 'guardian@example.com',
                    'password' => 'wrong-password',
                ]);

            $response->assertStatus(401)
                ->assertJsonPath('code', 'GUARDIAN_AUTH_INVALID_CREDENTIALS');
        }

        $this->travel(61)->seconds();

        $sixthResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/guardian/auth/login', [
                'email' => 'guardian@example.com',
                'password' => 'wrong-password',
            ]);

        $sixthResponse->assertStatus(401)
            ->assertJsonPath('code', 'GUARDIAN_AUTH_INVALID_CREDENTIALS');

        $blockedResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/guardian/auth/login', [
                'email' => 'guardian@example.com',
                'password' => 'wrong-password',
            ]);

        $blockedResponse->assertStatus(429)
            ->assertJsonPath('code', 'GUARDIAN_AUTH_RATE_LIMITED');
    }

    public function test_guardian_can_accept_link_invitation_and_list_active_children(): void
    {
        $kindergarten = Kindergarten::create([
            'name' => '星の幼稚園',
            'slug' => 'hoshi',
        ]);

        $staff = KindergartenStaff::create([
            'kindergarten_id' => $kindergarten->id,
            'name' => '保育士',
            'email' => 'staff@example.com',
            'email_normalized' => 'staff@example.com',
            'password_hash' => Hash::make('password-123'),
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $class = ChildClass::create([
            'kindergarten_id' => $kindergarten->id,
            'name' => 'ひよこ組',
        ]);

        $child = Child::create([
            'child_class_id' => $class->id,
            'name' => '鈴木一郎',
            'status' => 'enrolled',
        ]);

        $token = SecureToken::generate();

        ChildInvitation::create([
            'kindergarten_id' => $kindergarten->id,
            'child_id' => $child->id,
            'token_hash' => $token->hash(),
            'label' => '父用',
            'expires_at' => now()->addDays(7),
            'created_by_staff_id' => $staff->id,
        ]);

        $acceptResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($this->guardian),
        ])->postJson('/guardian/invitations/'.$token->plainText().'/accept');

        $acceptResponse->assertOk()
            ->assertJsonPath('guardian_id', $this->guardian->id)
            ->assertJsonPath('linked_child.id', $child->id)
            ->assertJsonPath('linked_child.kindergarten_id', $kindergarten->id)
            ->assertJsonStructure(['guardian_id', 'linked_child' => ['id', 'kindergarten_id'], 'linked_at']);

        $listResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($this->guardian),
        ])->getJson('/guardian/children');

        $listResponse->assertOk()
            ->assertJsonPath('data.0.child_id', $child->id)
            ->assertJsonPath('data.0.kindergarten_id', $kindergarten->id)
            ->assertJsonPath('data.0.child_name', '鈴木一郎')
            ->assertJsonPath('data.0.class_name', 'ひよこ組')
            ->assertJsonPath('data.0.label', '父用');
    }

    public function test_guardian_link_invitation_rejects_duplicate_active_links_and_used_invitation(): void
    {
        $kindergarten = Kindergarten::create([
            'name' => '青葉保育園',
            'slug' => 'aoba',
        ]);

        $staff = KindergartenStaff::create([
            'kindergarten_id' => $kindergarten->id,
            'name' => '先生',
            'email' => 'teacher@example.com',
            'email_normalized' => 'teacher@example.com',
            'password_hash' => Hash::make('password-123'),
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $class = ChildClass::create([
            'kindergarten_id' => $kindergarten->id,
            'name' => 'かき組',
        ]);

        $child = Child::create([
            'child_class_id' => $class->id,
            'name' => '佐藤花子',
            'status' => 'enrolled',
        ]);

        GuardianChild::create([
            'guardian_id' => $this->guardian->id,
            'child_id' => $child->id,
            'kindergarten_id' => $kindergarten->id,
            'label' => '母用',
            'linked_at' => now(),
        ]);

        $token = SecureToken::generate();

        $invitation = ChildInvitation::create([
            'kindergarten_id' => $kindergarten->id,
            'child_id' => $child->id,
            'token_hash' => $token->hash(),
            'label' => '母用',
            'expires_at' => now()->addDays(7),
            'created_by_staff_id' => $staff->id,
        ]);

        $duplicateResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($this->guardian),
        ])->postJson('/guardian/invitations/'.$token->plainText().'/accept');

        $duplicateResponse->assertStatus(409)
            ->assertJsonPath('code', 'GUARDIAN_CHILD_LINK_ALREADY_EXISTS');

        $invitation->refresh();
        $this->assertNull($invitation->used_at);
        $this->assertNull($invitation->used_by_guardian_id);

        $usedToken = SecureToken::generate();

        ChildInvitation::create([
            'kindergarten_id' => $kindergarten->id,
            'child_id' => $child->id,
            'token_hash' => $usedToken->hash(),
            'label' => '父用',
            'expires_at' => now()->addDays(7),
            'used_at' => now(),
            'used_by_guardian_id' => $this->guardian->id,
            'created_by_staff_id' => $staff->id,
        ]);

        $usedResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($this->guardian),
        ])->postJson('/guardian/invitations/'.$usedToken->plainText().'/accept');

        $usedResponse->assertStatus(409)
            ->assertJsonPath('code', 'INVITATION_ALREADY_USED');
    }

    public function test_guardian_link_invitation_rejects_invalid_expired_and_revoked_requests_without_side_effects(): void
    {
        $kindergarten = Kindergarten::create([
            'name' => '若葉保育園',
            'slug' => 'wakaba',
        ]);

        $staff = KindergartenStaff::create([
            'kindergarten_id' => $kindergarten->id,
            'name' => '先生',
            'email' => 'wakaba-staff@example.com',
            'email_normalized' => 'wakaba-staff@example.com',
            'password_hash' => Hash::make('password-123'),
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $class = ChildClass::create([
            'kindergarten_id' => $kindergarten->id,
            'name' => 'さくら組',
        ]);

        $child = Child::create([
            'child_class_id' => $class->id,
            'name' => '検証太郎',
            'status' => 'enrolled',
        ]);

        $activeToken = SecureToken::generate();
        $activeInvitation = ChildInvitation::create([
            'kindergarten_id' => $kindergarten->id,
            'child_id' => $child->id,
            'token_hash' => $activeToken->hash(),
            'label' => '父用',
            'expires_at' => now()->addDays(7),
            'created_by_staff_id' => $staff->id,
        ]);

        $initialLinkCount = GuardianChild::query()
            ->where('guardian_id', $this->guardian->id)
            ->where('child_id', $child->id)
            ->count();

        $invalidTokenResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($this->guardian),
        ])->postJson('/guardian/invitations/'.SecureToken::generate()->plainText().'/accept');

        $invalidTokenResponse->assertStatus(403)
            ->assertJsonPath('code', 'INVITATION_INVALID_OR_EXPIRED');

        $activeInvitation->refresh();
        $this->assertNull($activeInvitation->used_at);
        $this->assertNull($activeInvitation->used_by_guardian_id);
        $this->assertSame($initialLinkCount, GuardianChild::query()
            ->where('guardian_id', $this->guardian->id)
            ->where('child_id', $child->id)
            ->count());

        $expiredToken = SecureToken::generate();
        $expiredInvitation = ChildInvitation::create([
            'kindergarten_id' => $kindergarten->id,
            'child_id' => $child->id,
            'token_hash' => $expiredToken->hash(),
            'label' => '母用',
            'expires_at' => now()->subMinute(),
            'created_by_staff_id' => $staff->id,
        ]);

        $expiredResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($this->guardian),
        ])->postJson('/guardian/invitations/'.$expiredToken->plainText().'/accept');

        $expiredResponse->assertStatus(403)
            ->assertJsonPath('code', 'INVITATION_INVALID_OR_EXPIRED');

        $expiredInvitation->refresh();
        $this->assertNull($expiredInvitation->used_at);
        $this->assertNull($expiredInvitation->used_by_guardian_id);
        $this->assertSame($initialLinkCount, GuardianChild::query()
            ->where('guardian_id', $this->guardian->id)
            ->where('child_id', $child->id)
            ->count());

        $revokedToken = SecureToken::generate();
        $revokedInvitation = ChildInvitation::create([
            'kindergarten_id' => $kindergarten->id,
            'child_id' => $child->id,
            'token_hash' => $revokedToken->hash(),
            'label' => '祖父母用',
            'expires_at' => now()->addDays(7),
            'revoked_at' => now(),
            'created_by_staff_id' => $staff->id,
        ]);

        $revokedResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($this->guardian),
        ])->postJson('/guardian/invitations/'.$revokedToken->plainText().'/accept');

        $revokedResponse->assertStatus(403)
            ->assertJsonPath('code', 'INVITATION_INVALID_OR_EXPIRED');

        $revokedInvitation->refresh();
        $this->assertNull($revokedInvitation->used_at);
        $this->assertNull($revokedInvitation->used_by_guardian_id);
        $this->assertSame($initialLinkCount, GuardianChild::query()
            ->where('guardian_id', $this->guardian->id)
            ->where('child_id', $child->id)
            ->count());

    }

    public function test_guardian_link_invitation_rejects_unauthenticated_request_without_side_effects(): void
    {
        $kindergarten = Kindergarten::create([
            'name' => '若葉保育園',
            'slug' => 'wakaba-unauth',
        ]);

        $staff = KindergartenStaff::create([
            'kindergarten_id' => $kindergarten->id,
            'name' => '先生',
            'email' => 'wakaba-unauth-staff@example.com',
            'email_normalized' => 'wakaba-unauth-staff@example.com',
            'password_hash' => Hash::make('password-123'),
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $class = ChildClass::create([
            'kindergarten_id' => $kindergarten->id,
            'name' => 'さくら組',
        ]);

        $child = Child::create([
            'child_class_id' => $class->id,
            'name' => '未認証検証太郎',
            'status' => 'enrolled',
        ]);

        $unauthToken = SecureToken::generate();
        $unauthInvitation = ChildInvitation::create([
            'kindergarten_id' => $kindergarten->id,
            'child_id' => $child->id,
            'token_hash' => $unauthToken->hash(),
            'label' => '兄用',
            'expires_at' => now()->addDays(7),
            'created_by_staff_id' => $staff->id,
        ]);

        $initialLinkCount = GuardianChild::query()
            ->where('guardian_id', $this->guardian->id)
            ->where('child_id', $child->id)
            ->count();

        $unauthResponse = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/guardian/invitations/'.$unauthToken->plainText().'/accept');

        $unauthResponse->assertStatus(401)
            ->assertJsonPath('code', 'STAFF_AUTH_REQUIRED');

        $unauthInvitation->refresh();
        $this->assertNull($unauthInvitation->used_at);
        $this->assertNull($unauthInvitation->used_by_guardian_id);
        $this->assertSame($initialLinkCount, GuardianChild::query()
            ->where('guardian_id', $this->guardian->id)
            ->where('child_id', $child->id)
            ->count());
    }

    public function test_guardian_email_verification_notification_and_verification_work(): void
    {
        $this->guardian->forceFill(['email_verified_at' => null])->save();

        $loginResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/guardian/auth/login', [
                'email' => 'guardian@example.com',
                'password' => 'password-123',
            ]);

        $token = $loginResponse->json('access_token');

        $notificationResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/guardian/auth/email/verification-notification');

        $notificationResponse->assertStatus(202)
            ->assertJsonPath('queued', true);

        $hash = sha1($this->guardian->email);
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            [
                'id' => $this->guardian->id,
                'hash' => $hash,
            ],
        );

        $verifyResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson($verificationUrl);

        $verifyResponse->assertOk()
            ->assertJsonPath('email_verified_at', $this->guardian->fresh()->email_verified_at->toIso8601String());
    }

    public function test_guardian_email_verification_rejects_tampered_expired_and_cross_guardian_urls(): void
    {
        $this->guardian->forceFill(['email_verified_at' => null])->save();

        $otherGuardian = Guardian::create([
            'name' => '別保護者',
            'email' => 'other-guardian@example.com',
            'password_hash' => Hash::make('password-123'),
            'email_verified_at' => null,
        ]);

        $guardianHash = sha1($this->guardian->email);
        $validUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            [
                'id' => $this->guardian->id,
                'hash' => $guardianHash,
            ],
        );

        $tamperedUrl = str_replace($guardianHash, sha1('tampered@example.com'), $validUrl);

        $tamperedResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson($tamperedUrl);

        $tamperedResponse->assertStatus(403);
        $this->assertNull($this->guardian->fresh()->email_verified_at);

        $expiredUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            [
                'id' => $this->guardian->id,
                'hash' => $guardianHash,
            ],
        );

        $expiredResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson($expiredUrl);

        $expiredResponse->assertStatus(403);
        $this->assertNull($this->guardian->fresh()->email_verified_at);

        $crossGuardianUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            [
                'id' => $otherGuardian->id,
                'hash' => $guardianHash,
            ],
        );

        $crossGuardianResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson($crossGuardianUrl);

        $crossGuardianResponse->assertStatus(403);
        $this->assertNull($this->guardian->fresh()->email_verified_at);
        $this->assertNull($otherGuardian->fresh()->email_verified_at);
    }

    private function extractRefreshToken(TestResponse $response): string
    {
        $setCookieHeader = $response->headers->get('set-cookie');

        $this->assertIsString($setCookieHeader);

        preg_match('/refresh_token=([^;]+)/', $setCookieHeader, $matches);

        $this->assertNotEmpty($matches[1] ?? []);

        return urldecode($matches[1]);
    }
}
