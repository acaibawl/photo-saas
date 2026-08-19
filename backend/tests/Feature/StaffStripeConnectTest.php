<?php

namespace Tests\Feature;

use App\Application\Kindergarten\StripeConnectService;
use App\Domain\Staff\StaffRole;
use App\Models\Kindergarten;
use App\Models\KindergartenStaff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StaffStripeConnectTest extends TestCase
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

        config()->set('services.stripe.secret', 'sk_test_123');

        $this->kindergartenA = Kindergarten::create([
            'name' => 'ひかり保育園',
            'slug' => 'hikari',
        ]);

        $this->kindergartenB = Kindergarten::create([
            'name' => 'みらい幼稚園',
            'slug' => 'mirai',
        ]);

        $this->ownerA = $this->createStaff($this->kindergartenA, 'owner-a@example.com', StaffRole::Owner);
        $this->staffA = $this->createStaff($this->kindergartenA, 'staff-a@example.com', StaffRole::Staff);
        $this->ownerB = $this->createStaff($this->kindergartenB, 'owner-b@example.com', StaffRole::Owner);
    }

    public function test_staff_api_requires_authentication(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson('/staff/stripe/connect/status');

        $response->assertStatus(401)
            ->assertJsonPath('code', 'STAFF_AUTH_REQUIRED');
    }

    public function test_non_owner_cannot_access_stripe_connect_endpoints(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->staffA))
            ->postJson('/staff/stripe/connect/onboarding-link', [
                'return_url' => 'https://example.com/return',
                'refresh_url' => 'https://example.com/refresh',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'STAFF_ROLE_FORBIDDEN');
    }

    public function test_onboarding_link_requires_https_urls(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->ownerA))
            ->postJson('/staff/stripe/connect/onboarding-link', [
                'return_url' => 'http://example.com/return',
                'refresh_url' => 'https://example.com/refresh',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.return_url.0', 'return url は次のいずれかで始まる必要があります。：https://');
    }

    public function test_status_returns_default_values_when_account_is_missing(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->ownerB))
            ->getJson('/staff/stripe/connect/status');

        $response->assertOk()
            ->assertJsonPath('stripe_account_id', null)
            ->assertJsonPath('charges_enabled', false)
            ->assertJsonPath('payouts_enabled', false)
            ->assertJsonPath('onboarding_completed_at', null)
            ->assertJsonPath('requirements_due', []);
    }

    public function test_owner_can_create_onboarding_link_and_fetch_status(): void
    {
        Http::fake([
            'https://api.stripe.com/v1/accounts' => Http::response([
                'id' => 'acct_123456789',
            ], 200),
            'https://api.stripe.com/v1/account_links' => Http::response([
                'url' => 'https://connect.stripe.com/setup/s/acct_123456789',
                'expires_at' => 1_735_689_600,
            ], 200),
            'https://api.stripe.com/v1/accounts/acct_123456789' => Http::response([
                'id' => 'acct_123456789',
                'charges_enabled' => true,
                'payouts_enabled' => false,
                'requirements' => [
                    'currently_due' => ['external_account'],
                ],
            ], 200),
        ]);

        $onboardingResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->postJson('/staff/stripe/connect/onboarding-link', [
                'return_url' => 'https://example.com/return',
                'refresh_url' => 'https://example.com/refresh',
            ]);

        $onboardingResponse->assertOk()
            ->assertJsonPath('stripe_account_id', 'acct_123456789')
            ->assertJsonPath('onboarding_url', 'https://connect.stripe.com/setup/s/acct_123456789')
            ->assertJsonPath('expires_at', Carbon::createFromTimestampUTC(1_735_689_600)->toIso8601String());

        $this->kindergartenA->refresh();
        self::assertSame('acct_123456789', $this->kindergartenA->stripe_account_id);
        self::assertIsString($this->kindergartenA->stripe_account_creation_idempotency_key);
        self::assertNotSame('', trim((string) $this->kindergartenA->stripe_account_creation_idempotency_key));

        $statusResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->getJson('/staff/stripe/connect/status');

        $statusResponse->assertOk()
            ->assertJsonPath('stripe_account_id', 'acct_123456789')
            ->assertJsonPath('charges_enabled', true)
            ->assertJsonPath('payouts_enabled', false)
            ->assertJsonPath('onboarding_completed_at', null)
            ->assertJsonPath('requirements_due.0', 'external_account');

        Http::assertSentCount(3);
        Http::assertSent(function (HttpRequest $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.stripe.com/v1/accounts'
                && $request['country'] === 'JP'
                && $request['type'] === 'standard'
                && is_string($request->hasHeader('Idempotency-Key') ? $request->header('Idempotency-Key')[0] ?? null : null)
                && $request->hasHeader('Idempotency-Key');
        });
        Http::assertSent(function (HttpRequest $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.stripe.com/v1/account_links'
                && $request['account'] === 'acct_123456789'
                && $request['type'] === 'account_onboarding'
                && $request['return_url'] === 'https://example.com/return'
                && $request['refresh_url'] === 'https://example.com/refresh';
        });
    }

    public function test_sales_availability_is_disabled_before_onboarding(): void
    {
        $notReadyResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->getJson('/staff/sales/availability');

        $notReadyResponse->assertOk()
            ->assertJsonPath('sales_enabled', false)
            ->assertJsonPath('reason_code', 'STRIPE_ONBOARDING_INCOMPLETE')
            ->assertJsonPath('reason_message', 'Stripe onboarding is not completed');
    }

    public function test_webhook_accepts_any_valid_signature_when_multiple_v1_values_are_present(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_123');

        $this->kindergartenA->forceFill([
            'stripe_account_id' => 'acct_987654321',
        ])->save();

        $payload = json_encode([
            'id' => 'evt_account_updated_1',
            'created' => 1_755_500_001,
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => 'acct_987654321',
                    'charges_enabled' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $timestamp = (string) time();
        $validSignature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_123');
        $invalidSignature = hash_hmac('sha256', $timestamp.'.'.'not-the-same-payload', 'whsec_test_123');

        app(StripeConnectService::class)
            ->handleAccountUpdatedWebhook($payload, 't='.$timestamp.',v1='.$validSignature.',v1='.$invalidSignature);

        $this->kindergartenA->refresh();
        self::assertNotNull($this->kindergartenA->stripe_onboarding_completed_at);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_123');

        $this->kindergartenA->forceFill([
            'stripe_account_id' => 'acct_987654321',
        ])->save();

        $payload = json_encode([
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => 'acct_987654321',
                    'charges_enabled' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe webhook signature is invalid');

        app(StripeConnectService::class)
            ->handleAccountUpdatedWebhook($payload, 't=1234567890,v1=invalid');
    }

    public function test_webhook_ignores_unrelated_event_types(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_123');

        $this->kindergartenA->forceFill([
            'stripe_account_id' => 'acct_987654321',
            'stripe_onboarding_completed_at' => null,
        ])->save();

        $payload = json_encode([
            'type' => 'charge.succeeded',
            'data' => [
                'object' => [
                    'id' => 'acct_987654321',
                    'charges_enabled' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        app(StripeConnectService::class)
            ->handleAccountUpdatedWebhook($payload, $this->signedStripeSignature($payload, 'whsec_test_123'));

        $this->kindergartenA->refresh();
        self::assertNull($this->kindergartenA->stripe_onboarding_completed_at);
    }

    public function test_webhook_ignores_unknown_account_id(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_123');

        $this->kindergartenA->forceFill([
            'stripe_account_id' => 'acct_987654321',
            'stripe_onboarding_completed_at' => null,
        ])->save();

        $payload = json_encode([
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => 'acct_unknown_123',
                    'charges_enabled' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        app(StripeConnectService::class)
            ->handleAccountUpdatedWebhook($payload, $this->signedStripeSignature($payload, 'whsec_test_123'));

        $this->kindergartenA->refresh();
        self::assertNull($this->kindergartenA->stripe_onboarding_completed_at);
    }

    public function test_webhook_rejects_old_signature_timestamp(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_123');

        $this->kindergartenA->forceFill([
            'stripe_account_id' => 'acct_987654321',
            'stripe_onboarding_completed_at' => null,
        ])->save();

        $payload = json_encode([
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => 'acct_987654321',
                    'charges_enabled' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $expiredTimestamp = (string) (time() - 301);
        $expiredSignature = hash_hmac('sha256', $expiredTimestamp.'.'.$payload, 'whsec_test_123');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe webhook signature is invalid');

        app(StripeConnectService::class)
            ->handleAccountUpdatedWebhook($payload, 't='.$expiredTimestamp.',v1='.$expiredSignature);
    }

    public function test_webhook_resets_onboarding_completion_when_charges_are_disabled(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_123');

        $this->kindergartenA->forceFill([
            'stripe_account_id' => 'acct_987654321',
            'stripe_onboarding_completed_at' => null,
        ])->save();

        $enabledPayload = json_encode([
            'id' => 'evt_account_updated_enabled',
            'created' => 1_755_500_002,
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => 'acct_987654321',
                    'charges_enabled' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        app(StripeConnectService::class)
            ->handleAccountUpdatedWebhook($enabledPayload, $this->signedStripeSignature($enabledPayload, 'whsec_test_123'));

        $this->kindergartenA->refresh();
        self::assertNotNull($this->kindergartenA->stripe_onboarding_completed_at);

        $disabledPayload = json_encode([
            'id' => 'evt_account_updated_disabled',
            'created' => 1_755_500_003,
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => 'acct_987654321',
                    'charges_enabled' => false,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        app(StripeConnectService::class)
            ->handleAccountUpdatedWebhook($disabledPayload, $this->signedStripeSignature($disabledPayload, 'whsec_test_123'));

        $this->kindergartenA->refresh();
        self::assertNull($this->kindergartenA->stripe_onboarding_completed_at);
    }

    public function test_webhook_ignores_duplicate_events(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_123');

        $this->kindergartenA->forceFill([
            'stripe_account_id' => 'acct_987654321',
        ])->save();

        $enabledPayload = json_encode([
            'id' => 'evt_duplicate_account_updated',
            'created' => 1_755_500_004,
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => 'acct_987654321',
                    'charges_enabled' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $disabledPayload = json_encode([
            'id' => 'evt_duplicate_account_updated',
            'created' => 1_755_500_004,
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => 'acct_987654321',
                    'charges_enabled' => false,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $service = app(StripeConnectService::class);
        $service->handleAccountUpdatedWebhook($enabledPayload, $this->signedStripeSignature($enabledPayload, 'whsec_test_123'));
        $service->handleAccountUpdatedWebhook($disabledPayload, $this->signedStripeSignature($disabledPayload, 'whsec_test_123'));

        $this->kindergartenA->refresh();
        self::assertNotNull($this->kindergartenA->stripe_onboarding_completed_at);
        $this->assertDatabaseCount('stripe_webhook_events', 1);
    }

    public function test_webhook_ignores_events_older_than_the_latest_event_for_account(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_123');

        $this->kindergartenA->forceFill([
            'stripe_account_id' => 'acct_987654321',
        ])->save();

        $enabledPayload = json_encode([
            'id' => 'evt_new_account_updated',
            'created' => 1_755_500_006,
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => 'acct_987654321',
                    'charges_enabled' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $disabledPayload = json_encode([
            'id' => 'evt_old_account_updated',
            'created' => 1_755_500_005,
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => 'acct_987654321',
                    'charges_enabled' => false,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $service = app(StripeConnectService::class);
        $service->handleAccountUpdatedWebhook($enabledPayload, $this->signedStripeSignature($enabledPayload, 'whsec_test_123'));
        $service->handleAccountUpdatedWebhook($disabledPayload, $this->signedStripeSignature($disabledPayload, 'whsec_test_123'));

        $this->kindergartenA->refresh();
        self::assertNotNull($this->kindergartenA->stripe_onboarding_completed_at);
        $this->assertDatabaseCount('stripe_webhook_events', 2);
    }

    public function test_sales_availability_is_disabled_when_charges_are_disabled(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_123');

        $this->kindergartenA->forceFill([
            'stripe_account_id' => 'acct_987654321',
            'stripe_onboarding_completed_at' => now(),
        ])->save();

        Http::fake([
            'https://api.stripe.com/v1/accounts/acct_987654321*' => Http::response([
                'id' => 'acct_987654321',
                'charges_enabled' => false,
                'payouts_enabled' => true,
                'requirements' => [
                    'currently_due' => [],
                ],
            ], 200),
        ]);

        $disabledResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->getJson('/staff/sales/availability');

        $disabledResponse->assertOk()
            ->assertJsonPath('sales_enabled', false)
            ->assertJsonPath('reason_code', 'STRIPE_CHARGES_DISABLED')
            ->assertJsonPath('reason_message', 'Stripe charges are not enabled');
    }

    public function test_sales_availability_is_incomplete_when_charges_are_enabled_but_onboarding_is_not_completed(): void
    {
        $this->kindergartenA->forceFill([
            'stripe_account_id' => 'acct_987654321',
            'stripe_onboarding_completed_at' => null,
        ])->save();

        Http::fake([
            'https://api.stripe.com/v1/accounts/acct_987654321*' => Http::response([
                'id' => 'acct_987654321',
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'requirements' => [
                    'currently_due' => [],
                ],
            ], 200),
        ]);

        $response = $this->withHeaders($this->authHeaders($this->ownerA))
            ->getJson('/staff/sales/availability');

        $response->assertOk()
            ->assertJsonPath('sales_enabled', false)
            ->assertJsonPath('reason_code', 'STRIPE_ONBOARDING_INCOMPLETE')
            ->assertJsonPath('reason_message', 'Stripe onboarding is not completed');
    }

    public function test_sales_availability_is_enabled_when_onboarding_and_charges_are_ready(): void
    {
        $this->kindergartenA->forceFill([
            'stripe_account_id' => 'acct_987654321',
            'stripe_onboarding_completed_at' => now(),
        ])->save();

        Http::fake([
            'https://api.stripe.com/v1/accounts/acct_987654321*' => Http::response([
                'id' => 'acct_987654321',
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'requirements' => [
                    'currently_due' => [],
                ],
            ], 200),
        ]);

        $enabledResponse = $this->withHeaders($this->authHeaders($this->ownerA))
            ->getJson('/staff/sales/availability');

        $enabledResponse->assertOk()
            ->assertJsonPath('sales_enabled', true)
            ->assertJsonPath('reason_code', null)
            ->assertJsonPath('reason_message', null);
    }

    public function test_stripe_api_failures_return_502(): void
    {
        Http::fake([
            'https://api.stripe.com/v1/accounts' => Http::response([
                'error' => [
                    'message' => 'Stripe unavailable',
                ],
            ], 500),
        ]);

        $response = $this->withHeaders($this->authHeaders($this->ownerA))
            ->postJson('/staff/stripe/connect/onboarding-link', [
                'return_url' => 'https://example.com/return',
                'refresh_url' => 'https://example.com/refresh',
            ]);

        $response->assertStatus(502)
            ->assertJsonPath('code', 'STRIPE_API_ERROR');
    }

    public function test_onboarding_link_returns_502_when_account_links_response_has_missing_expires_at(): void
    {
        Http::fake([
            'https://api.stripe.com/v1/accounts' => Http::response([
                'id' => 'acct_123456789',
            ], 200),
            'https://api.stripe.com/v1/account_links' => Http::response([
                'url' => 'https://connect.stripe.com/setup/s/acct_123456789',
            ], 200),
        ]);

        $response = $this->withHeaders($this->authHeaders($this->ownerA))
            ->postJson('/staff/stripe/connect/onboarding-link', [
                'return_url' => 'https://example.com/return',
                'refresh_url' => 'https://example.com/refresh',
            ]);

        $response->assertStatus(502)
            ->assertJsonPath('code', 'STRIPE_API_ERROR');
    }

    public function test_onboarding_link_returns_502_when_stripe_response_has_invalid_url(): void
    {
        Http::fake([
            'https://api.stripe.com/v1/accounts' => Http::response([
                'id' => 'acct_123456789',
            ], 200),
            'https://api.stripe.com/v1/account_links' => Http::response([
                'url' => '',
                'expires_at' => 1_735_689_600,
            ], 200),
        ]);

        $response = $this->withHeaders($this->authHeaders($this->ownerA))
            ->postJson('/staff/stripe/connect/onboarding-link', [
                'return_url' => 'https://example.com/return',
                'refresh_url' => 'https://example.com/refresh',
            ]);

        $response->assertStatus(502)
            ->assertJsonPath('code', 'STRIPE_API_ERROR');
    }

    public function test_onboarding_link_reuses_persisted_kindergarten_idempotency_key(): void
    {
        $this->kindergartenA->forceFill([
            'stripe_account_id' => null,
            'stripe_account_creation_idempotency_key' => 'idem-kindergarten-a-fixed-key',
        ])->save();

        Http::fake([
            'https://api.stripe.com/v1/accounts' => Http::response([
                'id' => 'acct_reused_123',
            ], 200),
            'https://api.stripe.com/v1/account_links' => Http::response([
                'url' => 'https://connect.stripe.com/setup/s/acct_reused_123',
                'expires_at' => 1_735_689_600,
            ], 200),
        ]);

        $response = $this->withHeaders($this->authHeaders($this->ownerA))
            ->postJson('/staff/stripe/connect/onboarding-link', [
                'return_url' => 'https://example.com/return',
                'refresh_url' => 'https://example.com/refresh',
            ]);

        $response->assertOk()
            ->assertJsonPath('stripe_account_id', 'acct_reused_123')
            ->assertJsonPath('onboarding_url', 'https://connect.stripe.com/setup/s/acct_reused_123');

        Http::assertSent(function (HttpRequest $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.stripe.com/v1/accounts'
                && ($request->header('Idempotency-Key')[0] ?? null) === 'idem-kindergarten-a-fixed-key';
        });

        $this->kindergartenA->refresh();
        self::assertSame('acct_reused_123', $this->kindergartenA->stripe_account_id);
        self::assertSame('idem-kindergarten-a-fixed-key', $this->kindergartenA->stripe_account_creation_idempotency_key);
    }

    private function createStaff(Kindergarten $kindergarten, string $email, StaffRole $role): KindergartenStaff
    {
        return KindergartenStaff::create([
            'kindergarten_id' => $kindergarten->id,
            'name' => $role === StaffRole::Owner ? '園長' : 'スタッフ',
            'email' => $email,
            'email_normalized' => $email,
            'password_hash' => Hash::make('password-123'),
            'role' => $role,
            'joined_at' => now(),
        ]);
    }

    private function signedStripeSignature(string $payload, string $secret): string
    {
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return 't='.$timestamp.',v1='.$signature;
    }

    private function authHeaders(KindergartenStaff $staff): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.JWTAuth::fromUser($staff),
        ];
    }
}
