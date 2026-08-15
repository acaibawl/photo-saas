<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Child;
use App\Models\ChildClass;
use App\Models\Entitlement;
use App\Models\Guardian;
use App\Models\GuardianChild;
use App\Models\Kindergarten;
use App\Models\KindergartenStaff;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class GuardianPurchaseDownloadTest extends TestCase
{
    use RefreshDatabase;

    private Kindergarten $kindergarten;

    private KindergartenStaff $staff;

    private Guardian $guardian;

    private Child $linkedChild;

    private Child $otherChild;

    private Album $album;

    private Photo $visiblePhoto;

    private Photo $hiddenPhoto;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        config()->set('services.stripe.secret', 'sk_test_123');

        $this->kindergarten = Kindergarten::create([
            'name' => 'ひかり保育園',
            'slug' => 'hikari',
            'stripe_account_id' => 'acct_123456789',
            'stripe_onboarding_completed_at' => now(),
        ]);

        $this->staff = KindergartenStaff::create([
            'kindergarten_id' => $this->kindergarten->id,
            'name' => '園長',
            'email' => 'staff@example.com',
            'email_normalized' => 'staff@example.com',
            'password_hash' => Hash::make('password-123'),
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $childClass = ChildClass::create([
            'kindergarten_id' => $this->kindergarten->id,
            'name' => 'ひよこ組',
        ]);

        $otherClass = ChildClass::create([
            'kindergarten_id' => $this->kindergarten->id,
            'name' => 'うさぎ組',
        ]);

        $this->linkedChild = Child::create([
            'child_class_id' => $childClass->id,
            'name' => '山田花子',
            'status' => 'enrolled',
        ]);

        $this->otherChild = Child::create([
            'child_class_id' => $otherClass->id,
            'name' => '佐藤太郎',
            'status' => 'enrolled',
        ]);

        $this->guardian = Guardian::create([
            'name' => '保護者太郎',
            'email' => 'guardian@example.com',
            'password_hash' => Hash::make('password-123'),
            'email_verified_at' => now(),
        ]);

        GuardianChild::create([
            'guardian_id' => $this->guardian->id,
            'child_id' => $this->linkedChild->id,
            'kindergarten_id' => $this->kindergarten->id,
            'label' => '父',
            'linked_at' => now(),
        ]);

        $this->album = Album::create([
            'kindergarten_id' => $this->kindergarten->id,
            'title' => '夏祭り',
            'event_date' => '2026-08-01',
        ]);

        $this->visiblePhoto = Photo::create([
            'kindergarten_id' => $this->kindergarten->id,
            'album_id' => $this->album->id,
            'storage_path' => 'photos/originals/visible.jpg',
            'preview_path' => 'photos/previews/visible.jpg',
            'price' => 1200,
            'file_key' => 'visible-photo',
            'preview_status' => 'ready',
            'uploaded_by_staff_id' => $this->staff->id,
        ]);

        $this->hiddenPhoto = Photo::create([
            'kindergarten_id' => $this->kindergarten->id,
            'album_id' => $this->album->id,
            'storage_path' => 'photos/originals/hidden.jpg',
            'preview_path' => 'photos/previews/hidden.jpg',
            'price' => 1600,
            'file_key' => 'hidden-photo',
            'preview_status' => 'ready',
            'uploaded_by_staff_id' => $this->staff->id,
        ]);

        $this->visiblePhoto->taggedChildren()->sync([$this->linkedChild->id]);
        $this->hiddenPhoto->taggedChildren()->sync([$this->otherChild->id]);

        Storage::disk('s3')->put('photos/previews/visible.jpg', 'preview-visible');
        Storage::disk('s3')->put('photos/previews/hidden.jpg', 'preview-hidden');
        Storage::disk('s3')->put('photos/originals/visible.jpg', 'original-visible');
    }

    public function test_guardian_can_create_checkout_session_and_list_orders(): void
    {
        config()->set('purchase.platform_fee_rate', 0.15);
        config()->set('purchase.platform_fee_min_amount', 300);
        config()->set('purchase.platform_fee_max_amount', 3000);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/pay/cs_test_123',
            ], 200),
        ]);

        $checkoutResponse = $this->withHeaders($this->guardianAuthHeaders())
            ->postJson('/guardian/purchases/checkout-session', [
                'photo_ids' => [$this->visiblePhoto->id],
                'checkout_amount' => 1200,
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
            ]);

        $checkoutResponse->assertOk()
            ->assertJsonPath('total_amount', 1200)
            ->assertJsonPath('currency', 'jpy')
            ->assertJsonPath('checkout_session_id', 'cs_test_123')
            ->assertJsonPath('checkout_url', 'https://checkout.stripe.com/pay/cs_test_123');

        $orderId = $checkoutResponse->json('order_id');
        $this->assertIsString($orderId);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'guardian_id' => $this->guardian->id,
            'kindergarten_id' => $this->kindergarten->id,
            'status' => 'pending',
            'total_amount' => 1200,
            'platform_fee_amount' => 300,
            'stripe_checkout_session_id' => 'cs_test_123',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'photo_id' => $this->visiblePhoto->id,
            'price' => 1200,
        ]);

        $ordersResponse = $this->withHeaders($this->guardianAuthHeaders())
            ->getJson('/guardian/orders?status=pending');

        $ordersResponse->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.order_id', $orderId)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.items.0.photo_id', $this->visiblePhoto->id);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && $request['success_url'] === 'https://example.com/success'
                && $request['cancel_url'] === 'https://example.com/cancel'
                && $request['client_reference_id'] !== ''
                && $request['payment_intent_data']['application_fee_amount'] === 300
                && $request['line_items'][0]['price_data']['unit_amount'] === 1200;
        });
    }

    public function test_platform_fee_uses_rate_when_within_min_max_bounds(): void
    {
        config()->set('purchase.platform_fee_rate', 0.1);
        config()->set('purchase.platform_fee_min_amount', 300);
        config()->set('purchase.platform_fee_max_amount', 3000);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_456',
                'url' => 'https://checkout.stripe.com/pay/cs_test_456',
            ], 200),
        ]);

        $response = $this->withHeaders($this->guardianAuthHeaders())
            ->postJson('/guardian/purchases/checkout-session', [
                'photo_ids' => [$this->visiblePhoto->id],
                'checkout_amount' => 1200,
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('orders', [
            'stripe_checkout_session_id' => 'cs_test_456',
            'platform_fee_amount' => 300,
        ]);
    }

    public function test_platform_fee_uses_upper_bound_when_rate_exceeds_it(): void
    {
        config()->set('purchase.platform_fee_rate', 0.5);
        config()->set('purchase.platform_fee_min_amount', 300);
        config()->set('purchase.platform_fee_max_amount', 3000);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_789',
                'url' => 'https://checkout.stripe.com/pay/cs_test_789',
            ], 200),
        ]);

        $highValuePhoto = Photo::create([
            'kindergarten_id' => $this->kindergarten->id,
            'album_id' => $this->album->id,
            'storage_path' => 'photos/originals/high-value.jpg',
            'preview_path' => 'photos/previews/high-value.jpg',
            'price' => 10000,
            'file_key' => 'high-value-photo',
            'preview_status' => 'ready',
            'uploaded_by_staff_id' => $this->staff->id,
        ]);
        $highValuePhoto->taggedChildren()->sync([$this->linkedChild->id]);

        $response = $this->withHeaders($this->guardianAuthHeaders())
            ->postJson('/guardian/purchases/checkout-session', [
                'photo_ids' => [$highValuePhoto->id],
                'checkout_amount' => 10000,
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('orders', [
            'stripe_checkout_session_id' => 'cs_test_789',
            'platform_fee_amount' => 3000,
        ]);
    }

    public function test_checkout_session_rejects_invisible_photo_and_amount_mismatch(): void
    {
        $invisibleResponse = $this->withHeaders($this->guardianAuthHeaders())
            ->postJson('/guardian/purchases/checkout-session', [
                'photo_ids' => [$this->hiddenPhoto->id],
                'checkout_amount' => 1600,
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
            ]);

        $invisibleResponse->assertStatus(403)
            ->assertJsonPath('code', 'PHOTO_PURCHASE_NOT_ALLOWED');

        $mismatchResponse = $this->withHeaders($this->guardianAuthHeaders())
            ->postJson('/guardian/purchases/checkout-session', [
                'photo_ids' => [$this->visiblePhoto->id],
                'checkout_amount' => 999,
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
            ]);

        $mismatchResponse->assertStatus(409)
            ->assertJsonPath('code', 'CHECKOUT_AMOUNT_MISMATCH');
    }

    public function test_guardian_can_list_purchased_photos_and_download_after_unlink(): void
    {
        $order = Order::create([
            'guardian_id' => $this->guardian->id,
            'kindergarten_id' => $this->kindergarten->id,
            'status' => 'paid',
            'total_amount' => 1200,
            'platform_fee_amount' => 0,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'photo_id' => $this->visiblePhoto->id,
            'price' => 1200,
        ]);

        Entitlement::create([
            'order_item_id' => $orderItem->id,
            'guardian_id' => $this->guardian->id,
            'photo_id' => $this->visiblePhoto->id,
            'granted_at' => now()->subDay(),
        ]);

        $link = GuardianChild::query()
            ->where('guardian_id', $this->guardian->id)
            ->where('child_id', $this->linkedChild->id)
            ->firstOrFail();

        $link->forceFill([
            'unlinked_at' => now(),
        ])->save();

        $listResponse = $this->withHeaders($this->guardianAuthHeaders())
            ->getJson('/guardian/purchased-photos?album_id='.$this->album->id.'&event_date_from=2026-08-01&event_date_to=2026-08-31');

        $listResponse->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.photo_id', $this->visiblePhoto->id)
            ->assertJsonPath('data.0.album_id', $this->album->id)
            ->assertJsonPath('data.0.downloadable', true)
            ->assertJsonPath('data.0.event_date', '2026-08-01');

        $downloadResponse = $this->withHeaders($this->guardianAuthHeaders())
            ->postJson('/guardian/photos/'.$this->visiblePhoto->id.'/download-url');

        $downloadResponse->assertOk()
            ->assertJsonStructure(['download_url', 'expires_at']);

        $downloadUrl = $downloadResponse->json('download_url');
        $expiresAt = $downloadResponse->json('expires_at');

        $this->assertIsString($downloadUrl);
        $this->assertMatchesRegularExpression('/^https?:\/\/[^\s]+$/', $downloadUrl);

        $this->assertIsString($expiresAt);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $expiresAt);

        $missingDownloadResponse = $this->withHeaders($this->guardianAuthHeaders())
            ->postJson('/guardian/photos/'.$this->hiddenPhoto->id.'/download-url');

        $missingDownloadResponse->assertStatus(404)
            ->assertJsonPath('code', 'ENTITLEMENT_NOT_FOUND');
    }

    public function test_checkout_session_is_blocked_when_sales_are_disabled(): void
    {
        $this->kindergarten->forceFill([
            'stripe_onboarding_completed_at' => null,
        ])->save();

        $response = $this->withHeaders($this->guardianAuthHeaders())
            ->postJson('/guardian/purchases/checkout-session', [
                'photo_ids' => [$this->visiblePhoto->id],
                'checkout_amount' => 1200,
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'SALES_DISABLED_FOR_KINDERGARTEN');
    }

    private function guardianAuthHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($this->guardian),
        ];
    }
}
