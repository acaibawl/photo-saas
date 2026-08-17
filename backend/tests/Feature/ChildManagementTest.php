<?php

namespace Tests\Feature;

use App\Domain\Child\ChildStatus;
use App\Domain\Staff\StaffRole;
use App\Models\Child;
use App\Models\ChildClass;
use App\Models\Kindergarten;
use App\Models\KindergartenStaff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ChildManagementTest extends TestCase
{
    use RefreshDatabase;

    private Kindergarten $kindergartenA;

    private Kindergarten $kindergartenB;

    private KindergartenStaff $staffA;

    private KindergartenStaff $staffB;

    private Child $childA;

    private Child $childB;

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

        $this->childA = Child::create([
            'child_class_id' => $this->getOrCreateChildClassIdFor($this->kindergartenA->id, 'うさぎ組'),
            'name' => '山田花子',
            'status' => ChildStatus::Enrolled,
        ]);

        $this->childB = Child::create([
            'child_class_id' => $this->getOrCreateChildClassIdFor($this->kindergartenA->id, 'ひよこ組'),
            'name' => '佐藤太郎',
            'status' => ChildStatus::Graduated,
        ]);

        $this->otherKindergartenChild = Child::create([
            'child_class_id' => $this->getOrCreateChildClassIdFor($this->kindergartenB->id, 'ぞう組'),
            'name' => '他園児',
            'status' => ChildStatus::Enrolled,
        ]);
    }

    public function test_staff_api_requires_authentication_for_children(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson('/staff/children');

        $response->assertStatus(401)
            ->assertJsonPath('code', 'STAFF_AUTH_REQUIRED');
    }

    public function test_child_class_is_materialized_as_a_kindergarten_scoped_entity(): void
    {
        $createResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/children', [
                'name' => '新規園児',
                'child_class_id' => $this->getOrCreateChildClassIdFor($this->kindergartenA->id, 'ぱんだ組'),
            ]);

        $createdClassId = $this->getChildClassIdFor($this->kindergartenA->id, 'ぱんだ組');
        self::assertNotNull($createdClassId);

        $createResponse->assertCreated()
            ->assertJsonPath('class_name', 'ぱんだ組')
            ->assertJsonPath('class_id', $createdClassId);

        $createdChildId = $createResponse->json('id');
        $this->assertDatabaseHas('child_classes', [
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => 'ぱんだ組',
        ]);
        $this->assertDatabaseHas('children', [
            'id' => $createdChildId,
            'child_class_id' => $createdClassId,
        ]);
    }

    public function test_staff_can_create_list_show_and_update_children(): void
    {
        $createResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/children', [
                'name' => '新規園児',
                'child_class_id' => $this->getOrCreateChildClassIdFor($this->kindergartenA->id, 'ぱんだ組'),
            ]);

        $createResponse->assertCreated()
            ->assertJsonPath('kindergarten_id', $this->kindergartenA->id)
            ->assertJsonPath('name', '新規園児')
            ->assertJsonPath('class_name', 'ぱんだ組')
            ->assertJsonPath('status', 'enrolled')
            ->assertJsonMissingPath('updated_at');

        $createdChildId = $createResponse->json('id');
        self::assertNotNull($createdChildId);

        $listResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/children?page=1&per_page=2');

        $listResponse->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);

        $showResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/children/'.$this->childA->id);

        $showResponse->assertOk()
            ->assertJsonPath('id', $this->childA->id)
            ->assertJsonPath('status', 'enrolled')
            ->assertJsonPath('updated_at', $this->childA->updated_at?->toIso8601String());

        $updateResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->patchJson('/staff/children/'.$this->childA->id, [
                'name' => '山田花子 改',
                'child_class_id' => $this->getOrCreateChildClassIdFor($this->kindergartenA->id, 'きりん組'),
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('id', $this->childA->id)
            ->assertJsonPath('name', '山田花子 改')
            ->assertJsonPath('class_name', 'きりん組')
            ->assertJsonPath('status', 'enrolled');
    }

    public function test_list_children_applies_filters(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/children?status=graduated&child_class_id='.$this->getOrCreateChildClassIdFor($this->kindergartenA->id, 'ひよこ組').'&keyword=太郎');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $this->childB->id)
            ->assertJsonPath('data.0.status', 'graduated');
    }

    public function test_staff_can_update_child_status_and_same_status_is_accepted(): void
    {
        $graduateResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->patchJson('/staff/children/'.$this->childA->id.'/status', [
                'status' => 'graduated',
            ]);

        $graduateResponse->assertOk()
            ->assertJsonPath('id', $this->childA->id)
            ->assertJsonPath('status', 'graduated');

        $sameStatusResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->patchJson('/staff/children/'.$this->childA->id.'/status', [
                'status' => 'graduated',
            ]);

        $sameStatusResponse->assertOk()
            ->assertJsonPath('id', $this->childA->id)
            ->assertJsonPath('status', 'graduated');
    }

    public function test_staff_can_restore_a_graduated_child_to_enrolled(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->patchJson('/staff/children/'.$this->childB->id.'/status', [
                'status' => 'enrolled',
            ]);

        $response->assertOk()
            ->assertJsonPath('id', $this->childB->id)
            ->assertJsonPath('status', 'enrolled');
    }

    public function test_staff_can_change_a_graduated_child_to_withdrawn(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->patchJson('/staff/children/'.$this->childB->id.'/status', [
                'status' => 'withdrawn',
            ]);

        $response->assertOk()
            ->assertJsonPath('id', $this->childB->id)
            ->assertJsonPath('status', 'withdrawn');
    }

    public function test_staff_can_restore_a_withdrawn_child_to_enrolled(): void
    {
        $this->childA->update(['status' => ChildStatus::Withdrawn]);

        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->patchJson('/staff/children/'.$this->childA->id.'/status', [
                'status' => 'enrolled',
            ]);

        $response->assertOk()
            ->assertJsonPath('id', $this->childA->id)
            ->assertJsonPath('status', 'enrolled');
    }

    public function test_staff_can_change_a_withdrawn_child_to_graduated(): void
    {
        $this->childA->update(['status' => ChildStatus::Withdrawn]);

        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->patchJson('/staff/children/'.$this->childA->id.'/status', [
                'status' => 'graduated',
            ]);

        $response->assertOk()
            ->assertJsonPath('id', $this->childA->id)
            ->assertJsonPath('status', 'graduated');
    }

    public function test_cross_tenant_and_missing_child_are_handled_distinctly(): void
    {
        $crossTenantClassId = $this->getOrCreateChildClassIdFor($this->kindergartenB->id, '他園組');

        $createResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/children', [
                'name' => '越境園児',
                'child_class_id' => $crossTenantClassId,
            ]);

        $createResponse->assertStatus(403)
            ->assertJsonPath('code', 'TENANT_SCOPE_VIOLATION');

        $updateResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->patchJson('/staff/children/'.$this->childA->id, [
                'child_class_id' => $crossTenantClassId,
            ]);

        $updateResponse->assertStatus(403)
            ->assertJsonPath('code', 'TENANT_SCOPE_VIOLATION');

        $crossTenantResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/children/'.$this->otherKindergartenChild->id);

        $crossTenantResponse->assertStatus(403)
            ->assertJsonPath('code', 'TENANT_SCOPE_VIOLATION');

        $missingResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/children/'.str()->ulid());

        $missingResponse->assertStatus(404)
            ->assertJsonPath('code', 'CHILD_NOT_FOUND');
    }

    private function authHeaders(KindergartenStaff $staff): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($staff),
        ];
    }

    private function getOrCreateChildClassIdFor(string $kindergartenId, string $className): ?string
    {
        $record = ChildClass::query()
            ->firstOrCreate([
                'kindergarten_id' => $kindergartenId,
                'name' => $className,
            ]);

        return $record->id;
    }

    private function getChildClassIdFor(string $kindergartenId, string $className): ?string
    {
        $record = ChildClass::query()
            ->where('kindergarten_id', $kindergartenId)
            ->where('name', $className)
            ->first();

        return $record?->id;
    }
}
