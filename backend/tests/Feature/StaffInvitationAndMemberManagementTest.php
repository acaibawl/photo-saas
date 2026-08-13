<?php

namespace Tests\Feature;

use App\Domain\Shared\SecureToken;
use App\Domain\Staff\StaffRole;
use App\Models\Kindergarten;
use App\Models\KindergartenStaff;
use App\Models\StaffInvitation;
use App\Models\StaffRefreshToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StaffInvitationAndMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    private Kindergarten $kindergartenA;

    private Kindergarten $kindergartenB;

    private KindergartenStaff $ownerA;

    private KindergartenStaff $staffA;

    private KindergartenStaff $ownerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kindergartenA = Kindergarten::create([
            'name' => 'ひかり保育園',
            'slug' => 'hikari',
        ]);

        $this->kindergartenB = Kindergarten::create([
            'name' => 'みらい幼稚園',
            'slug' => 'mirai',
        ]);

        $this->ownerA = KindergartenStaff::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => '園長A',
            'email' => 'owner-a@example.com',
            'email_normalized' => 'owner-a@example.com',
            'password_hash' => Hash::make('password-123'),
            'role' => StaffRole::Owner,
            'joined_at' => now(),
        ]);

        $this->staffA = KindergartenStaff::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => 'スタッフA',
            'email' => 'staff-a@example.com',
            'email_normalized' => 'staff-a@example.com',
            'password_hash' => Hash::make('password-123'),
            'role' => StaffRole::Staff,
            'joined_at' => now(),
        ]);

        $this->ownerB = KindergartenStaff::create([
            'kindergarten_id' => $this->kindergartenB->id,
            'name' => '園長B',
            'email' => 'owner-b@example.com',
            'email_normalized' => 'owner-b@example.com',
            'password_hash' => Hash::make('password-123'),
            'role' => StaffRole::Owner,
            'joined_at' => now(),
        ]);
    }

    public function test_staff_api_requires_authentication(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/staff/staff-invitations', [
                'name' => '招待対象',
                'email' => 'invited@example.com',
                'role' => 'staff',
            ]);

        $response->assertStatus(401)
            ->assertJsonPath('code', 'STAFF_AUTH_REQUIRED');
    }

    public function test_owner_can_create_and_list_staff_invitations(): void
    {
        $createResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->postJson('/staff/staff-invitations', [
                'name' => '招待対象',
                'email' => 'invited@example.com',
                'role' => 'staff',
                'expires_in_days' => 10,
            ]);

        $createResponse->assertCreated()
            ->assertJsonPath('invited_email', 'invited@example.com')
            ->assertJsonMissingPath('raw_token');

        $invitationId = $createResponse->json('invitation_id');
        self::assertNotNull($invitationId);

        $listResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->getJson('/staff/staff-invitations?status=pending');

        $listResponse->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invitation_id', $invitationId)
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_non_owner_cannot_manage_staff_invitations(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/staff-invitations', [
                'name' => '招待対象',
                'email' => 'invited@example.com',
                'role' => 'staff',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'STAFF_ROLE_FORBIDDEN');
    }

    public function test_create_staff_invitation_rejects_duplicate_pending_invitation(): void
    {
        $this->createInvitationRecord($this->kindergartenA->id, 'dup@example.com', StaffRole::Staff, [
            'name' => '先行招待',
            'created_by_staff_id' => $this->ownerA->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($this->ownerA))
            ->postJson('/staff/staff-invitations', [
                'name' => '後続招待',
                'email' => 'dup@example.com',
                'role' => 'staff',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'STAFF_INVITATION_ALREADY_EXISTS');
    }

    public function test_owner_can_revoke_pending_invitation_and_cannot_revoke_accepted_invitation(): void
    {
        [$pendingInvitation] = $this->createInvitationRecord($this->kindergartenA->id, 'pending@example.com', StaffRole::Staff, [
            'created_by_staff_id' => $this->ownerA->id,
        ]);

        [$acceptedInvitation] = $this->createInvitationRecord($this->kindergartenA->id, 'accepted@example.com', StaffRole::Staff, [
            'accepted_at' => now(),
            'created_by_staff_id' => $this->ownerA->id,
            'accepted_staff_id' => $this->staffA->id,
        ]);

        $revokeResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->postJson('/staff/staff-invitations/'.$pendingInvitation->id.'/revoke');

        $revokeResponse->assertOk()
            ->assertJsonPath('invitation_id', $pendingInvitation->id);

        $acceptedRevokeResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->postJson('/staff/staff-invitations/'.$acceptedInvitation->id.'/revoke');

        $acceptedRevokeResponse->assertStatus(409)
            ->assertJsonPath('code', 'STAFF_INVITATION_ALREADY_ACCEPTED');
    }

    public function test_owner_cannot_revoke_missing_invitation(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->ownerA))
            ->postJson('/staff/staff-invitations/'.str()->uuid().'/revoke');

        $response->assertStatus(404)
            ->assertJsonPath('code', 'STAFF_INVITATION_NOT_FOUND');
    }

    public function test_public_preview_returns_invitation_data_and_rejects_invalid_token(): void
    {
        [$invitation, $rawToken] = $this->createInvitationRecord($this->kindergartenA->id, 'preview@example.com', StaffRole::Staff, [
            'name' => 'プレビュー対象',
            'created_by_staff_id' => $this->ownerA->id,
        ]);

        $previewResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson('/public/staff-invitations/'.$rawToken);

        $previewResponse->assertOk()
            ->assertJsonPath('invited_name', $invitation->name)
            ->assertJsonPath('invited_email', 'preview@example.com');

        $invalidResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson('/public/staff-invitations/invalid-token');

        $invalidResponse->assertStatus(403)
            ->assertJsonPath('code', 'STAFF_INVITATION_INVALID_OR_EXPIRED');
    }

    public function test_accept_invitation_creates_staff_and_returns_tokens(): void
    {
        [$invitation, $rawToken] = $this->createInvitationRecord($this->kindergartenA->id, 'new-staff@example.com', StaffRole::Staff, [
            'name' => '新規スタッフ',
            'created_by_staff_id' => $this->ownerA->id,
        ]);

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/public/staff-invitations/'.$rawToken.'/accept', [
                'password' => 'password-123',
                'password_confirmation' => 'password-123',
            ]);

        $response->assertOk()
            ->assertJsonPath('staff.kindergarten_id', $this->kindergartenA->id)
            ->assertJsonPath('staff.role', 'staff')
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);

        $invitation->refresh();
        self::assertNotNull($invitation->accepted_at);

        $this->assertDatabaseHas('kindergarten_staff', [
            'kindergarten_id' => $this->kindergartenA->id,
            'email_normalized' => 'new-staff@example.com',
            'role' => 'staff',
        ]);
    }

    public function test_accept_owner_initial_setup_flow_uses_existing_owner_account(): void
    {
        $this->ownerA->forceFill([
            'password_hash' => Hash::make('initial-password-123'),
            'joined_at' => null,
            'invited_at' => now(),
        ])->save();

        $token = SecureToken::generate();

        $ownerInvitation = StaffInvitation::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => $this->ownerA->name,
            'email' => $this->ownerA->email,
            'email_normalized' => $this->ownerA->email_normalized,
            'role' => StaffRole::Owner,
            'token_hash' => $token->hash(),
            'expires_at' => now()->addDays(7),
            'created_by_staff_id' => $this->ownerA->id,
        ]);

        $acceptResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/public/staff-invitations/'.$token->plainText().'/accept', [
                'password' => 'new-owner-password-123',
                'password_confirmation' => 'new-owner-password-123',
            ]);

        $acceptResponse->assertOk()
            ->assertJsonPath('staff.id', $this->ownerA->id)
            ->assertJsonPath('staff.role', 'owner');

        $ownerInvitation->refresh();
        self::assertSame($this->ownerA->id, $ownerInvitation->accepted_staff_id);

        $loginResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/staff/auth/login', [
                'email' => $this->ownerA->email,
                'password' => 'new-owner-password-123',
            ]);

        $loginResponse->assertOk();
    }

    public function test_staff_member_list_and_detail_are_scoped_by_kindergarten(): void
    {
        $listResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->getJson('/staff/staff-members?status=active');

        $listResponse->assertOk()
            ->assertJsonPath('meta.total', 2);

        $detailResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->getJson('/staff/staff-members/'.$this->staffA->id);

        $detailResponse->assertOk()
            ->assertJsonPath('staff_id', $this->staffA->id);

        $crossTenantResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->getJson('/staff/staff-members/'.$this->ownerB->id);

        $crossTenantResponse->assertStatus(404)
            ->assertJsonPath('code', 'STAFF_MEMBER_NOT_FOUND');
    }

    public function test_staff_role_change_prevents_self_change(): void
    {
        KindergartenStaff::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => '副園長',
            'email' => 'owner-sub@example.com',
            'email_normalized' => 'owner-sub@example.com',
            'password_hash' => Hash::make('password-123'),
            'role' => StaffRole::Owner,
            'joined_at' => now(),
        ]);

        $selfResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->patchJson('/staff/staff-members/'.$this->ownerA->id.'/role', [
                'role' => 'staff',
            ]);

        $selfResponse->assertStatus(409)
            ->assertJsonPath('code', 'STAFF_ROLE_CHANGE_SELF_FORBIDDEN');
    }

    public function test_staff_role_change_prevents_last_owner_demotion(): void
    {
        $lastOwnerDemoteResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->patchJson('/staff/staff-members/'.$this->ownerA->id.'/role', [
                'role' => 'staff',
            ]);

        $lastOwnerDemoteResponse->assertStatus(409)
            ->assertJsonPath('code', 'OWNER_MINIMUM_REQUIRED');
    }

    public function test_staff_role_change_allows_promoting_owner_back_to_owner(): void
    {
        $secondaryOwner = KindergartenStaff::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => '副園長',
            'email' => 'owner-sub@example.com',
            'email_normalized' => 'owner-sub@example.com',
            'password_hash' => Hash::make('password-123'),
            'role' => StaffRole::Owner,
            'joined_at' => now(),
        ]);

        $demoteResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->patchJson('/staff/staff-members/'.$secondaryOwner->id.'/role', [
                'role' => 'staff',
            ]);

        $demoteResponse->assertOk()
            ->assertJsonPath('role', 'staff');

        $lastOwnerPromotionResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->patchJson('/staff/staff-members/'.$secondaryOwner->id.'/role', [
                'role' => 'owner',
            ]);

        $lastOwnerPromotionResponse->assertOk()
            ->assertJsonPath('role', 'owner');
    }

    public function test_deactivate_enforces_constraints_revokes_tokens_and_reactivate_restores_status(): void
    {
        $selfDeactivate = $this->withHeaders($this->authHeaders($this->ownerA))
            ->postJson('/staff/staff-members/'.$this->ownerA->id.'/deactivate');

        $selfDeactivate->assertStatus(409)
            ->assertJsonPath('code', 'STAFF_DEACTIVATE_SELF_FORBIDDEN');

        $token = SecureToken::generate();
        $refreshToken = StaffRefreshToken::create([
            'kindergarten_staff_id' => $this->staffA->id,
            'token_hash' => $token->hash(),
            'family_id' => 'family-deactivate-1',
            'family_expires_at' => now()->addDays(14),
            'expires_at' => now()->addDays(14),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $deactivateResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->postJson('/staff/staff-members/'.$this->staffA->id.'/deactivate');

        $deactivateResponse->assertOk()
            ->assertJsonPath('staff_id', $this->staffA->id);

        $refreshToken->refresh();
        self::assertNotNull($refreshToken->revoked_at);

        $reactivateResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->postJson('/staff/staff-members/'.$this->staffA->id.'/reactivate');

        $reactivateResponse->assertOk()
            ->assertJsonPath('staff_id', $this->staffA->id);

        $this->staffA->refresh();
        self::assertNull($this->staffA->deactivated_at);
    }

    /**
     * @return array{0: StaffInvitation, 1: string}
     */
    private function createInvitationRecord(string $kindergartenId, string $email, StaffRole $role, array $overrides = []): array
    {
        $token = SecureToken::generate();

        $invitation = StaffInvitation::create(array_merge([
            'kindergarten_id' => $kindergartenId,
            'name' => 'テスト招待',
            'email' => $email,
            'email_normalized' => mb_strtolower($email),
            'role' => $role,
            'token_hash' => $token->hash(),
            'expires_at' => now()->addDays(7),
            'created_by_staff_id' => $this->ownerA->id,
            'accepted_at' => null,
            'revoked_at' => null,
            'accepted_staff_id' => null,
        ], $overrides));

        return [$invitation, $token->plainText()];
    }

    private function authHeaders(KindergartenStaff $staff): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($staff),
        ];
    }
}
