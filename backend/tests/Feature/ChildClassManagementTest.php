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

class ChildClassManagementTest extends TestCase
{
    use RefreshDatabase;

    private Kindergarten $kindergartenA;

    private Kindergarten $kindergartenB;

    private KindergartenStaff $staffA;

    private KindergartenStaff $staffB;

    private ChildClass $classA;

    private ChildClass $classB;

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

        $this->classA = ChildClass::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => 'うさぎ組',
        ]);

        $this->classB = ChildClass::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => 'ひよこ組',
        ]);
    }

    public function test_staff_api_requires_authentication_for_child_classes(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson('/staff/child-classes');

        $response->assertStatus(401)
            ->assertJsonPath('code', 'STAFF_AUTH_REQUIRED');
    }

    public function test_staff_can_create_list_show_and_update_child_classes(): void
    {
        $createResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/child-classes', [
                'name' => 'ぱんだ組',
            ]);

        $createResponse->assertCreated()
            ->assertJsonPath('kindergarten_id', $this->kindergartenA->id)
            ->assertJsonPath('name', 'ぱんだ組');

        $createdClassId = $createResponse->json('id');
        self::assertNotNull($createdClassId);

        $listResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/child-classes?page=1&per_page=10');

        $listResponse->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.name', 'うさぎ組');

        $showResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/child-classes/'.$this->classA->id);

        $showResponse->assertOk()
            ->assertJsonPath('id', $this->classA->id)
            ->assertJsonPath('name', 'うさぎ組');

        $updateResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->patchJson('/staff/child-classes/'.$this->classA->id, [
                'name' => 'きりん組',
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('id', $this->classA->id)
            ->assertJsonPath('name', 'きりん組');
    }

    public function test_duplicate_child_class_name_within_kindergarten_is_rejected(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/child-classes', [
                'name' => 'うさぎ組',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'CHILD_CLASS_NAME_ALREADY_EXISTS');
    }

    public function test_cross_tenant_and_missing_child_class_are_handled_distinctly(): void
    {
        $otherKindergartenClass = ChildClass::create([
            'kindergarten_id' => $this->kindergartenB->id,
            'name' => '他園組',
        ]);

        $crossTenantResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/child-classes/'.$otherKindergartenClass->id);

        $crossTenantResponse->assertStatus(403)
            ->assertJsonPath('code', 'TENANT_SCOPE_VIOLATION');

        $missingResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/child-classes/'.str()->ulid());

        $missingResponse->assertStatus(404)
            ->assertJsonPath('code', 'CHILD_CLASS_NOT_FOUND');
    }

    public function test_child_class_cannot_be_deleted_while_in_use(): void
    {
        $inUseClass = ChildClass::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => 'おひさま組',
        ]);

        Child::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'child_class_id' => $inUseClass->id,
            'name' => '山田花子',
            'class_name' => 'おひさま組',
            'status' => ChildStatus::Enrolled,
        ]);

        $inUseResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->deleteJson('/staff/child-classes/'.$inUseClass->id);

        $inUseResponse->assertStatus(409)
            ->assertJsonPath('code', 'CHILD_CLASS_IN_USE');

        $emptyClass = ChildClass::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => 'はな組',
        ]);

        $deleteResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->deleteJson('/staff/child-classes/'.$emptyClass->id);

        $deleteResponse->assertOk()
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('id', $emptyClass->id);
    }

    private function authHeaders(KindergartenStaff $staff): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($staff),
        ];
    }
}
