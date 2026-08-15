<?php

namespace Tests\Feature;

use App\Domain\Child\ChildStatus;
use App\Domain\Staff\StaffRole;
use App\Models\Album;
use App\Models\Child;
use App\Models\ChildClass;
use App\Jobs\ProcessUploadBatchJob;
use App\Models\Kindergarten;
use App\Models\KindergartenStaff;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StaffAlbumPhotoManagementTest extends TestCase
{
    use RefreshDatabase;

    private Kindergarten $kindergartenA;

    private Kindergarten $kindergartenB;

    private KindergartenStaff $staffA;

    private KindergartenStaff $staffB;

    private Child $childA;

    private Child $childB;

    private Child $otherKindergartenChild;

    private Album $albumA;

    private Album $otherKindergartenAlbum;

    private Photo $readyPhoto;

    private Photo $queuedPhoto;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        $this->kindergartenA = Kindergarten::create([
            'name' => 'ひかり保育園',
            'slug' => 'hikari',
        ]);

        $this->kindergartenB = Kindergarten::create([
            'name' => 'みらい幼稚園',
            'slug' => 'mirai',
        ]);

        $this->staffA = $this->createStaff($this->kindergartenA, 'staff-a@example.com');
        $this->staffB = $this->createStaff($this->kindergartenB, 'staff-b@example.com');

        $this->childA = $this->createChild($this->kindergartenA->id, '山田花子', 'うさぎ組');
        $this->childB = $this->createChild($this->kindergartenA->id, '佐藤太郎', 'ひよこ組');
        $this->otherKindergartenChild = $this->createChild($this->kindergartenB->id, '他園児', 'ぞう組');

        $this->albumA = Album::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'title' => '夏まつり',
            'event_date' => '2026-07-20',
        ]);

        $this->otherKindergartenAlbum = Album::create([
            'kindergarten_id' => $this->kindergartenB->id,
            'title' => '運動会',
            'event_date' => '2026-06-10',
        ]);

        $this->readyPhoto = Photo::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'album_id' => $this->albumA->id,
            'storage_path' => 'photos/originals/photo-a.jpg',
            'preview_path' => 'photos/previews/photo-a.jpg',
            'price' => 1200,
            'file_key' => 'photo-a',
            'preview_status' => 'ready',
            'uploaded_by_staff_id' => $this->staffA->id,
        ]);
        $this->readyPhoto->taggedChildren()->attach([$this->childA->id]);

        $this->queuedPhoto = Photo::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'album_id' => null,
            'storage_path' => 'photos/originals/photo-b.jpg',
            'preview_path' => null,
            'price' => null,
            'file_key' => 'photo-b',
            'preview_status' => 'queued',
            'uploaded_by_staff_id' => $this->staffA->id,
        ]);

        Photo::create([
            'kindergarten_id' => $this->kindergartenB->id,
            'album_id' => $this->otherKindergartenAlbum->id,
            'storage_path' => 'photos/originals/photo-c.jpg',
            'preview_path' => 'photos/previews/photo-c.jpg',
            'price' => 1500,
            'file_key' => 'photo-c',
            'preview_status' => 'ready',
            'uploaded_by_staff_id' => $this->staffB->id,
        ]);
    }

    public function test_staff_album_and_photo_endpoints_require_authentication(): void
    {
        $this->withHeaders(['Accept' => 'application/json'])
            ->getJson('/staff/photos')
            ->assertStatus(401)
            ->assertJsonPath('code', 'STAFF_AUTH_REQUIRED');

        $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/staff/albums', [
                'title' => '遠足',
                'event_date' => '2026-05-01',
            ])
            ->assertStatus(401)
            ->assertJsonPath('code', 'STAFF_AUTH_REQUIRED');
    }

    public function test_staff_can_create_album(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/albums', [
                'title' => '遠足',
                'event_date' => '2026-05-01',
            ]);

        $response->assertCreated()
            ->assertJsonPath('kindergarten_id', $this->kindergartenA->id)
            ->assertJsonPath('title', '遠足')
            ->assertJsonPath('event_date', '2026-05-01');

        $this->assertDatabaseHas('albums', [
            'kindergarten_id' => $this->kindergartenA->id,
            'title' => '遠足',
        ]);
    }

    public function test_staff_can_accept_photo_upload_batch_and_persist_jobs(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->post('/staff/photos/upload-batch', [
                'album_id' => $this->albumA->id,
                'price' => '900',
                'child_ids' => [$this->childA->id, $this->childB->id],
                'files' => [
                    UploadedFile::fake()->image('first.jpg'),
                    UploadedFile::fake()->image('second.png'),
                ],
            ], ['Accept' => 'application/json']);

        $response->assertStatus(202)
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('accepted_count', 2)
            ->assertJsonPath('total_files', 2);

        $batchId = $response->json('batch_id');
        self::assertNotNull($batchId);

        $this->assertDatabaseHas('upload_requests', [
            'id' => $batchId,
            'kindergarten_id' => $this->kindergartenA->id,
            'album_id' => $this->albumA->id,
            'price' => 900,
            'accepted_count' => 2,
            'total_files' => 2,
        ]);

        $this->assertDatabaseCount('upload_jobs', 2);
        self::assertCount(2, Storage::disk('s3')->allFiles('uploads/tmp/'.$this->kindergartenA->id.'/'.$batchId));
    }

    public function test_staff_can_process_and_query_upload_batch_status(): void
    {
        Queue::fake();

        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->post('/staff/photos/upload-batch', [
                'album_id' => $this->albumA->id,
                'price' => '900',
                'child_ids' => [$this->childA->id],
                'files' => [UploadedFile::fake()->image('batch-status.jpg')],
            ], ['Accept' => 'application/json']);

        $batchId = $response->json('batch_id');
        self::assertNotNull($batchId);

        $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/photos/upload-batch/'.$batchId)
            ->assertOk()
            ->assertJsonPath('batch_id', $batchId)
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('jobs.0.status', 'accepted');

        Queue::assertPushed(ProcessUploadBatchJob::class, fn (ProcessUploadBatchJob $job): bool => $job->uploadRequestId === $batchId);

        $job = new ProcessUploadBatchJob($batchId);
        $job->handle();

        $this->assertDatabaseHas('upload_requests', [
            'id' => $batchId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('upload_jobs', [
            'upload_request_id' => $batchId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('photos', [
            'kindergarten_id' => $this->kindergartenA->id,
            'album_id' => $this->albumA->id,
            'preview_status' => 'ready',
        ]);
    }

    public function test_upload_batch_rejects_cross_tenant_album_and_child_ids(): void
    {
        $crossTenantAlbumResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->post('/staff/photos/upload-batch', [
                'album_id' => $this->otherKindergartenAlbum->id,
                'files' => [UploadedFile::fake()->image('first.jpg')],
            ], ['Accept' => 'application/json']);

        $crossTenantAlbumResponse->assertStatus(403)
            ->assertJsonPath('code', 'TENANT_SCOPE_VIOLATION');

        $crossTenantChildResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->post('/staff/photos/upload-batch', [
                'files' => [UploadedFile::fake()->image('first.jpg')],
                'child_ids' => [$this->otherKindergartenChild->id],
            ], ['Accept' => 'application/json']);

        $crossTenantChildResponse->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_staff_can_list_and_show_photos_with_filters(): void
    {
        $filterResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/photos?album_id='.$this->albumA->id.'&child_id='.$this->childA->id.'&price_status=set&preview_status=ready');

        $filterResponse->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.photo_id', $this->readyPhoto->id)
            ->assertJsonPath('data.0.is_sellable', true)
            ->assertJsonPath('data.0.tagged_child_ids.0', $this->childA->id);

        $showResponse = $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/photos/'.$this->readyPhoto->id);

        $showResponse->assertOk()
            ->assertJsonPath('photo_id', $this->readyPhoto->id)
            ->assertJsonPath('album_id', $this->albumA->id)
            ->assertJsonPath('album_title', '夏まつり')
            ->assertJsonPath('price', 1200)
            ->assertJsonPath('is_sellable', true)
            ->assertJsonPath('tagged_children.0.child_id', $this->childA->id)
            ->assertJsonPath('tagged_children.0.class_name', 'うさぎ組');
    }

    public function test_photo_show_distinguishes_cross_tenant_and_missing_records(): void
    {
        $otherPhoto = Photo::query()->where('kindergarten_id', $this->kindergartenB->id)->firstOrFail();

        $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/photos/'.$otherPhoto->id)
            ->assertStatus(403)
            ->assertJsonPath('code', 'TENANT_SCOPE_VIOLATION');

        $this->withHeaders($this->authHeaders($this->staffA))
            ->getJson('/staff/photos/'.str()->ulid())
            ->assertStatus(404)
            ->assertJsonPath('code', 'ALBUM_OR_PHOTO_NOT_FOUND');
    }

    public function test_staff_can_update_ready_photo_album_price_and_tags(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->patchJson('/staff/photos/'.$this->readyPhoto->id, [
                'album_id' => null,
                'price' => 2000,
                'child_ids' => [$this->childA->id, $this->childB->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('photo_id', $this->readyPhoto->id)
            ->assertJsonPath('album_id', null)
            ->assertJsonPath('price', 2000)
            ->assertJsonPath('is_sellable', true)
            ->assertJsonCount(2, 'child_ids');

        $this->assertDatabaseHas('photos', [
            'id' => $this->readyPhoto->id,
            'album_id' => null,
            'price' => 2000,
        ]);
        $this->assertDatabaseCount('photo_child_tags', 2);
    }

    public function test_staff_cannot_update_photo_until_preview_is_ready(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->patchJson('/staff/photos/'.$this->queuedPhoto->id, [
                'price' => 1000,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'PHOTO_NOT_READY_FOR_UPDATE');
    }

    private function authHeaders(KindergartenStaff $staff): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($staff),
        ];
    }

    private function createStaff(Kindergarten $kindergarten, string $email): KindergartenStaff
    {
        return KindergartenStaff::create([
            'kindergarten_id' => $kindergarten->id,
            'name' => $email,
            'email' => $email,
            'email_normalized' => $email,
            'password_hash' => Hash::make('password-123'),
            'role' => StaffRole::Owner,
            'joined_at' => now(),
        ]);
    }

    private function createChild(string $kindergartenId, string $name, string $className): Child
    {
        return Child::create([
            'child_class_id' => ChildClass::query()->firstOrCreate([
                'kindergarten_id' => $kindergartenId,
                'name' => $className,
            ])->id,
            'name' => $name,
            'status' => ChildStatus::Enrolled,
        ]);
    }
}
