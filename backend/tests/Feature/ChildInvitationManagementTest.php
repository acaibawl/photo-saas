<?php

namespace Tests\Feature;

use App\Domain\Child\ChildStatus;
use App\Domain\Staff\StaffRole;
use App\Models\Child;
use App\Models\ChildClass;
use App\Models\ChildInvitation;
use App\Models\Kindergarten;
use App\Models\KindergartenStaff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ChildInvitationManagementTest extends TestCase
{
    use RefreshDatabase;

    private Kindergarten $kindergartenA;

    private Kindergarten $kindergartenB;

    private KindergartenStaff $staffA;

    private KindergartenStaff $staffB;

    private Child $childA;

    private Child $otherKindergartenChild;

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

        $this->staffA = KindergartenStaff::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => 'スタッフA',
            'email' => 'staff-a@example.com',
            'email_normalized' => 'staff-a@example.com',
            'password_hash' => Hash::make('password-123'),
            'role' => StaffRole::Owner,
            'joined_at' => now(),
        ]);

        $this->staffB = KindergartenStaff::create([
            'kindergarten_id' => $this->kindergartenB->id,
            'name' => 'スタッフB',
            'email' => 'staff-b@example.com',
            'email_normalized' => 'staff-b@example.com',
            'password_hash' => Hash::make('password-123'),
            'role' => StaffRole::Owner,
            'joined_at' => now(),
        ]);

        $classA = ChildClass::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => 'うさぎ組',
        ]);

        $classB = ChildClass::create([
            'kindergarten_id' => $this->kindergartenB->id,
            'name' => 'ぞう組',
        ]);

        $this->childA = Child::create([
            'child_class_id' => $classA->id,
            'name' => '山田花子',
            'status' => ChildStatus::Enrolled,
        ]);

        $this->otherKindergartenChild = Child::create([
            'child_class_id' => $classB->id,
            'name' => '他園児',
            'status' => ChildStatus::Enrolled,
        ]);
    }

    public function test_staff_api_requires_authentication_for_invitations(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson('/staff/children/'.$this->childA->id.'/invitations');

        $response->assertStatus(401)
            ->assertJsonPath('code', 'STAFF_AUTH_REQUIRED');
    }

    public function test_staff_can_create_invitation_with_hashed_token(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/children/'.$this->childA->id.'/invitations', [
                'label' => '父用',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['invitation_id', 'invite_url', 'token_expires_at', 'qr_payload']);

        $invitationId = $response->json('invitation_id');
        $inviteUrl = $response->json('invite_url');
        $rawToken = Str::afterLast($inviteUrl, '/');

        self::assertSame($inviteUrl, $response->json('qr_payload'));

        $invitation = ChildInvitation::query()->findOrFail($invitationId);
        self::assertNotSame($rawToken, $invitation->token_hash);
        self::assertTrue(hash_equals($invitation->token_hash, hash('sha256', $rawToken)));
        self::assertSame($this->childA->id, $invitation->child_id);
        self::assertTrue($invitation->expires_at->isBetween(now()->addDays(89), now()->addDays(91)));
    }

    public function test_create_invitation_validates_child_tenant_scope_and_existence(): void
    {
        $crossTenantResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/children/'.$this->otherKindergartenChild->id.'/invitations', [
                'label' => '父用',
            ]);

        $crossTenantResponse->assertStatus(403)
            ->assertJsonPath('code', 'TENANT_SCOPE_VIOLATION');

        $missingResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/children/'.str()->ulid().'/invitations', [
                'label' => '父用',
            ]);

        $missingResponse->assertStatus(404)
            ->assertJsonPath('code', 'CHILD_NOT_FOUND');
    }

    public function test_list_invitations_filters_by_status(): void
    {
        $active = $this->createInvitation($this->childA, $this->staffA, '父用');
        $used = $this->createInvitation($this->childA, $this->staffA, '母用');
        $used->forceFill(['used_at' => now()])->save();
        $revoked = $this->createInvitation($this->childA, $this->staffA, '祖父用');
        $revoked->forceFill(['revoked_at' => now()])->save();
        $expired = $this->createInvitation($this->childA, $this->staffA, '祖母用');
        $expired->forceFill(['expires_at' => now()->subDay()])->save();

        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/children/'.$this->childA->id.'/invitations?status=active');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invitation_id', $active->id);

        $usedResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/children/'.$this->childA->id.'/invitations?status=used');

        $usedResponse->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invitation_id', $used->id);

        $revokedResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/children/'.$this->childA->id.'/invitations?status=revoked');

        $revokedResponse->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invitation_id', $revoked->id);

        $expiredResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/children/'.$this->childA->id.'/invitations?status=expired');

        $expiredResponse->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invitation_id', $expired->id);
    }

    public function test_print_returns_pdf_with_matching_token(): void
    {
        [$invitation, $rawToken] = $this->createInvitationWithRawToken($this->childA, $this->staffA);

        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->get('/staff/invitations/'.$invitation->id.'/print?token='.$rawToken);

        $response->assertOk();
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_print_rejects_mismatched_token(): void
    {
        [$invitation] = $this->createInvitationWithRawToken($this->childA, $this->staffA);

        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->get('/staff/invitations/'.$invitation->id.'/print?token=invalid-token');

        $response->assertStatus(403)
            ->assertJsonPath('code', 'INVITATION_TOKEN_MISMATCH');
    }

    public function test_print_validates_tenant_scope_and_existence(): void
    {
        [$invitation, $rawToken] = $this->createInvitationWithRawToken($this->otherKindergartenChild, $this->staffB);

        $crossTenantResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->get('/staff/invitations/'.$invitation->id.'/print?token='.$rawToken);

        $crossTenantResponse->assertStatus(403)
            ->assertJsonPath('code', 'TENANT_SCOPE_VIOLATION');

        $missingResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->get('/staff/invitations/'.str()->ulid().'/print?token='.$rawToken);

        $missingResponse->assertStatus(404)
            ->assertJsonPath('code', 'INVITATION_NOT_FOUND');
    }

    public function test_revoke_is_idempotent_and_rejects_used_invitation(): void
    {
        $invitation = $this->createInvitation($this->childA, $this->staffA, '父用');

        $firstResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/invitations/'.$invitation->id.'/revoke', [
                'reason' => '入力ミス',
            ]);

        $firstResponse->assertOk()
            ->assertJsonPath('invitation_id', $invitation->id);
        self::assertNotNull($firstResponse->json('revoked_at'));

        $secondResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/invitations/'.$invitation->id.'/revoke');

        $secondResponse->assertOk()
            ->assertJsonPath('revoked_at', $firstResponse->json('revoked_at'));

        $used = $this->createInvitation($this->childA, $this->staffA, '母用');
        $used->forceFill(['used_at' => now()])->save();

        $usedResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/invitations/'.$used->id.'/revoke');

        $usedResponse->assertStatus(409)
            ->assertJsonPath('code', 'INVITATION_ALREADY_USED');
    }

    public function test_reissue_replaces_invitation_and_revokes_old_one(): void
    {
        [$invitation, $rawToken] = $this->createInvitationWithRawToken($this->childA, $this->staffA);

        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/invitations/'.$invitation->id.'/reissue');

        $response->assertCreated()
            ->assertJsonStructure(['invitation_id', 'invite_url', 'token_expires_at']);

        $newInvitationId = $response->json('invitation_id');
        self::assertNotSame($invitation->id, $newInvitationId);

        $newInvitation = ChildInvitation::query()->findOrFail($newInvitationId);
        self::assertSame($invitation->id, $newInvitation->reissued_from_invitation_id);
        self::assertSame($invitation->label, $newInvitation->label);

        $invitation->refresh();
        self::assertNotNull($invitation->revoked_at);

        $newInviteUrl = $response->json('invite_url');
        $newRawToken = Str::afterLast($newInviteUrl, '/');
        self::assertNotSame($rawToken, $newRawToken);
    }

    public function test_reissue_rejects_used_or_already_revoked_invitation(): void
    {
        $used = $this->createInvitation($this->childA, $this->staffA, '父用');
        $used->forceFill(['used_at' => now()])->save();

        $usedResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/invitations/'.$used->id.'/reissue');

        $usedResponse->assertStatus(409)
            ->assertJsonPath('code', 'INVITATION_ALREADY_USED');

        $revoked = $this->createInvitation($this->childA, $this->staffA, '母用');
        $revoked->forceFill(['revoked_at' => now()])->save();

        $revokedResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/invitations/'.$revoked->id.'/reissue');

        $revokedResponse->assertStatus(409)
            ->assertJsonPath('code', 'INVITATION_ALREADY_REVOKED');
    }

    public function test_reissue_limit_is_enforced_at_three_times(): void
    {
        $current = $this->createInvitation($this->childA, $this->staffA, '父用');

        for ($i = 0; $i < 3; $i++) {
            $response = $this->withHeaders($this->authHeaders($this->staffA))
                ->postJson('/staff/invitations/'.$current->id.'/reissue');

            $response->assertCreated();

            $current = ChildInvitation::query()->findOrFail($response->json('invitation_id'));
        }

        $limitExceededResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/invitations/'.$current->id.'/reissue');

        $limitExceededResponse->assertStatus(409)
            ->assertJsonPath('code', 'INVITATION_REISSUE_LIMIT_EXCEEDED');
    }

    private function createInvitation(Child $child, KindergartenStaff $staff, string $label): ChildInvitation
    {
        return ChildInvitation::create([
            'kindergarten_id' => $child->childClass->kindergarten_id,
            'child_id' => $child->id,
            'token_hash' => hash('sha256', Str::random(32)),
            'label' => $label,
            'expires_at' => now()->addDays(90),
            'created_by_staff_id' => $staff->id,
        ]);
    }

    /**
     * @return array{0: ChildInvitation, 1: string}
     */
    private function createInvitationWithRawToken(Child $child, KindergartenStaff $staff): array
    {
        $rawToken = Str::random(32);

        $invitation = ChildInvitation::create([
            'kindergarten_id' => $child->childClass->kindergarten_id,
            'child_id' => $child->id,
            'token_hash' => hash('sha256', $rawToken),
            'label' => '父用',
            'expires_at' => now()->addDays(90),
            'created_by_staff_id' => $staff->id,
        ]);

        return [$invitation, $rawToken];
    }

    private function authHeaders(KindergartenStaff $staff): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($staff),
        ];
    }
}
