<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Child;
use App\Models\ChildClass;
use App\Models\Guardian;
use App\Models\GuardianChild;
use App\Models\Kindergarten;
use App\Models\KindergartenStaff;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class GuardianPhotoViewingTest extends TestCase
{
    use RefreshDatabase;

    private Kindergarten $kindergartenA;

    private Kindergarten $kindergartenB;

    private Guardian $guardian;

    private Guardian $otherGuardian;

    private KindergartenStaff $staff;

    private Child $childA;

    private Child $childB;

    private Child $otherKindergartenChild;

    private Album $albumA;

    private Album $albumB;

    private Album $otherKindergartenAlbum;

    private Photo $visiblePhoto;

    private Photo $sharedAlbumPhoto;

    private Photo $otherKindergartenPhoto;

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

        $childClassA = ChildClass::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => 'ひよこ組',
        ]);

        $childClassB = ChildClass::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => 'うさぎ組',
        ]);

        $this->childA = Child::create([
            'child_class_id' => $childClassA->id,
            'name' => '山田花子',
            'status' => 'enrolled',
        ]);

        $this->childB = Child::create([
            'child_class_id' => $childClassB->id,
            'name' => '佐藤太郎',
            'status' => 'enrolled',
        ]);

        $otherKindergartenClass = ChildClass::create([
            'kindergarten_id' => $this->kindergartenB->id,
            'name' => 'ぞう組',
        ]);

        $this->otherKindergartenChild = Child::create([
            'child_class_id' => $otherKindergartenClass->id,
            'name' => '他園児',
            'status' => 'enrolled',
        ]);

        $this->staff = KindergartenStaff::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'name' => '園長',
            'email' => 'staff@example.com',
            'email_normalized' => 'staff@example.com',
            'password_hash' => password_hash('password-123', PASSWORD_DEFAULT),
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->guardian = Guardian::create([
            'name' => '保護者太郎',
            'email' => 'guardian@example.com',
            'password_hash' => password_hash('password-123', PASSWORD_DEFAULT),
            'email_verified_at' => now(),
        ]);

        $this->otherGuardian = Guardian::create([
            'name' => '別保護者',
            'email' => 'other-guardian@example.com',
            'password_hash' => password_hash('password-123', PASSWORD_DEFAULT),
            'email_verified_at' => now(),
        ]);

        GuardianChild::create([
            'guardian_id' => $this->guardian->id,
            'child_id' => $this->childA->id,
            'kindergarten_id' => $this->kindergartenA->id,
            'label' => '父',
            'linked_at' => now(),
        ]);

        GuardianChild::create([
            'guardian_id' => $this->guardian->id,
            'child_id' => $this->childB->id,
            'kindergarten_id' => $this->kindergartenA->id,
            'label' => '母',
            'linked_at' => now(),
        ]);

        GuardianChild::create([
            'guardian_id' => $this->otherGuardian->id,
            'child_id' => $this->childA->id,
            'kindergarten_id' => $this->kindergartenA->id,
            'label' => '祖父',
            'linked_at' => now(),
        ]);

        $this->albumA = Album::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'title' => '夏祭り',
            'event_date' => '2026-07-20',
        ]);

        $this->albumB = Album::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'title' => '遠足',
            'event_date' => '2026-08-01',
        ]);

        $this->otherKindergartenAlbum = Album::create([
            'kindergarten_id' => $this->kindergartenB->id,
            'title' => '他園アルバム',
            'event_date' => '2026-08-10',
        ]);

        $this->visiblePhoto = Photo::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'album_id' => $this->albumA->id,
            'storage_path' => 'photos/originals/visible.jpg',
            'preview_path' => 'photos/previews/visible.jpg',
            'price' => 1200,
            'file_key' => 'visible-photo',
            'preview_status' => 'ready',
            'uploaded_by_staff_id' => $this->staff->id,
        ]);

        $this->visiblePhoto->taggedChildren()->sync([$this->childA->id]);
        Storage::disk('s3')->put('photos/previews/visible.jpg', 'preview');

        $this->sharedAlbumPhoto = Photo::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'album_id' => $this->albumA->id,
            'storage_path' => 'photos/originals/shared.jpg',
            'preview_path' => 'photos/previews/shared.jpg',
            'price' => 1600,
            'file_key' => 'shared-photo',
            'preview_status' => 'ready',
            'uploaded_by_staff_id' => $this->staff->id,
        ]);

        $this->sharedAlbumPhoto->taggedChildren()->sync([$this->childA->id, $this->childB->id]);
        Storage::disk('s3')->put('photos/previews/shared.jpg', 'preview');

        $this->otherKindergartenPhoto = Photo::create([
            'kindergarten_id' => $this->kindergartenB->id,
            'album_id' => $this->otherKindergartenAlbum->id,
            'storage_path' => 'photos/originals/other.jpg',
            'preview_path' => 'photos/previews/other.jpg',
            'price' => 2500,
            'file_key' => 'other-photo',
            'preview_status' => 'ready',
            'uploaded_by_staff_id' => $this->staff->id,
        ]);

        $albumBPhoto = Photo::create([
            'kindergarten_id' => $this->kindergartenA->id,
            'album_id' => $this->albumB->id,
            'storage_path' => 'photos/originals/album-b.jpg',
            'preview_path' => 'photos/previews/album-b.jpg',
            'price' => 1900,
            'file_key' => 'album-b-photo',
            'preview_status' => 'ready',
            'uploaded_by_staff_id' => $this->staff->id,
        ]);
        $albumBPhoto->taggedChildren()->sync([$this->childB->id]);
        Storage::disk('s3')->put('photos/previews/album-b.jpg', 'preview');

        $this->otherKindergartenPhoto->taggedChildren()->sync([$this->otherKindergartenChild->id]);
        Storage::disk('s3')->put('photos/previews/other.jpg', 'preview');
    }

    public function test_guardian_can_list_active_albums_and_filter_by_child(): void
    {
        $response = $this->withHeaders($this->guardianAuthHeaders())
            ->getJson('/guardian/albums');

        $response->assertOk()
            ->assertJsonPath('data.0.album_id', $this->albumB->id)
            ->assertJsonPath('data.1.album_id', $this->albumA->id)
            ->assertJsonCount(2, 'data');

        $filteredResponse = $this->withHeaders($this->guardianAuthHeaders())
            ->getJson('/guardian/albums?child_id='.$this->childA->id);

        $filteredResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.album_id', $this->albumA->id);
    }

    public function test_guardian_can_list_show_and_refresh_preview_url_for_visible_photos(): void
    {
        $listResponse = $this->withHeaders($this->guardianAuthHeaders())
            ->getJson('/guardian/photos?child_id='.$this->childA->id.'&album_id='.$this->albumA->id);

        $listResponse->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.album_id', $this->albumA->id)
            ->assertJsonPath('data.0.tagged_child_ids.0', $this->childA->id);

        $detailResponse = $this->withHeaders($this->guardianAuthHeaders())
            ->getJson('/guardian/photos/'.$this->visiblePhoto->id);

        $detailResponse->assertOk()
            ->assertJsonPath('photo_id', $this->visiblePhoto->id)
            ->assertJsonPath('album.title', '夏祭り')
            ->assertJsonPath('tagged_children.0.child_id', $this->childA->id)
            ->assertJsonPath('tagged_children.0.name', '山田花子');

        $previewResponse = $this->withHeaders($this->guardianAuthHeaders())
            ->postJson('/guardian/photos/'.$this->visiblePhoto->id.'/preview-url');

        $previewResponse->assertOk()
            ->assertJsonPath('preview_url', $detailResponse->json('preview_url'))
            ->assertJsonPath('expires_at', $previewResponse->json('expires_at'));
    }

    public function test_guardian_gets_access_denied_for_other_child_and_not_found_for_missing_photo(): void
    {
        $otherChildResponse = $this->withHeaders($this->guardianAuthHeaders())
            ->getJson('/guardian/photos/'.$this->otherKindergartenPhoto->id);

        $otherChildResponse->assertStatus(403)
            ->assertJsonPath('code', 'PHOTO_ACCESS_DENIED');

        $missingResponse = $this->withHeaders($this->guardianAuthHeaders())
            ->getJson('/guardian/photos/01H8X6W4HB7N4M2YQ2F8Y3V3E1');

        $missingResponse->assertStatus(404)
            ->assertJsonPath('code', 'PHOTO_NOT_FOUND');
    }

    private function guardianAuthHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($this->guardian),
        ];
    }
}
