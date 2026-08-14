<?php

namespace Tests\Feature;

use App\Domain\Child\ChildStatus;
use App\Domain\Staff\StaffRole;
use App\Models\Child;
use App\Models\ChildClass;
use App\Models\Guardian;
use App\Models\GuardianChild;
use App\Models\Kindergarten;
use App\Models\KindergartenStaff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class GuardianLinkManagementTest extends TestCase
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

    public function test_staff_api_requires_authentication_for_guardian_links(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson('/staff/children/'.$this->childA->id.'/guardian-links');

        $response->assertStatus(401)
            ->assertJsonPath('code', 'STAFF_AUTH_REQUIRED');
    }

    public function test_staff_can_list_active_and_unlinked_guardian_links_for_a_child(): void
    {
        $guardianA = Guardian::create([
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password_hash' => Hash::make('password-123'),
        ]);

        $guardianB = Guardian::create([
            'name' => '山田花子',
            'email' => 'hanako@example.com',
            'password_hash' => Hash::make('password-123'),
        ]);

        $activeLink = GuardianChild::create([
            'guardian_id' => $guardianA->id,
            'child_id' => $this->childA->id,
            'kindergarten_id' => $this->kindergartenA->id,
            'label' => '父用',
            'linked_at' => now(),
        ]);

        $unlinkedLink = GuardianChild::create([
            'guardian_id' => $guardianB->id,
            'child_id' => $this->childA->id,
            'kindergarten_id' => $this->kindergartenA->id,
            'label' => '母用',
            'linked_at' => now()->subDay(),
            'unlinked_at' => now()->subHours(2),
            'unlinked_by_staff_id' => $this->staffA->id,
        ]);

        $activeResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/children/'.$this->childA->id.'/guardian-links');

        $activeResponse->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.link_id', $activeLink->id)
            ->assertJsonMissingPath('data.1');

        $allResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/children/'.$this->childA->id.'/guardian-links?include_unlinked=true');

        $allResponse->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.link_id', $activeLink->id)
            ->assertJsonPath('data.1.link_id', $unlinkedLink->id);
    }

    public function test_staff_can_unlink_and_restore_guardian_links(): void
    {
        $guardian = Guardian::create([
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password_hash' => Hash::make('password-123'),
        ]);

        $link = GuardianChild::create([
            'guardian_id' => $guardian->id,
            'child_id' => $this->childA->id,
            'kindergarten_id' => $this->kindergartenA->id,
            'label' => '父用',
            'linked_at' => now(),
        ]);

        $unlinkResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/guardian-links/'.$link->id.'/unlink', [
                'reason' => '入力ミス',
                'confirm_text' => 'UNLINK',
            ]);

        $unlinkResponse->assertOk()
            ->assertJsonPath('link_id', $link->id);

        self::assertNotNull($unlinkResponse->json('unlinked_at'));
        self::assertSame($this->staffA->id, $unlinkResponse->json('unlinked_by_staff_id'));

        $link->refresh();
        self::assertNotNull($link->unlinked_at);
        self::assertSame($this->staffA->id, $link->unlinked_by_staff_id);

        $alreadyUnlinkedResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/guardian-links/'.$link->id.'/unlink', [
                'confirm_text' => 'UNLINK',
            ]);

        $alreadyUnlinkedResponse->assertStatus(409)
            ->assertJsonPath('code', 'GUARDIAN_LINK_ALREADY_UNLINKED');

        $restoreResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/guardian-links/'.$link->id.'/restore');

        $restoreResponse->assertOk()
            ->assertJsonPath('link_id', $link->id);

        self::assertNull($restoreResponse->json('unlinked_at'));
        self::assertNotNull($restoreResponse->json('restored_at'));

        $link->refresh();
        self::assertNull($link->unlinked_at);
        self::assertNull($link->unlinked_by_staff_id);

        $alreadyActiveResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/guardian-links/'.$link->id.'/restore');

        $alreadyActiveResponse->assertStatus(409)
            ->assertJsonPath('code', 'GUARDIAN_LINK_NOT_UNLINKED');
    }

    public function test_guardian_link_endpoints_validate_tenant_scope_and_missing_records(): void
    {
        $guardian = Guardian::create([
            'name' => '他園保護者',
            'email' => 'other@example.com',
            'password_hash' => Hash::make('password-123'),
        ]);

        $crossTenantLink = GuardianChild::create([
            'guardian_id' => $guardian->id,
            'child_id' => $this->otherKindergartenChild->id,
            'kindergarten_id' => $this->kindergartenB->id,
            'label' => '保護者',
            'linked_at' => now(),
        ]);

        $crossTenantListResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/children/'.$this->otherKindergartenChild->id.'/guardian-links');

        $crossTenantListResponse->assertStatus(403)
            ->assertJsonPath('code', 'TENANT_SCOPE_VIOLATION');

        $missingResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/children/'.str()->ulid().'/guardian-links');

        $missingResponse->assertStatus(404)
            ->assertJsonPath('code', 'CHILD_NOT_FOUND');

        $crossTenantUnlinkResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/guardian-links/'.$crossTenantLink->id.'/unlink', [
                'confirm_text' => 'UNLINK',
            ]);

        $crossTenantUnlinkResponse->assertStatus(403)
            ->assertJsonPath('code', 'TENANT_SCOPE_VIOLATION');

        $missingLinkResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/guardian-links/'.str()->ulid().'/unlink', [
                'confirm_text' => 'UNLINK',
            ]);

        $missingLinkResponse->assertStatus(404)
            ->assertJsonPath('code', 'GUARDIAN_LINK_NOT_FOUND');
    }

    private function authHeaders(KindergartenStaff $staff): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($staff),
        ];
    }
}
