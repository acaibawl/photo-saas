<?php

namespace App\Application\Kindergarten;

use App\Models\Kindergarten;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

final class StripeConnectService
{
    private const STRIPE_API_BASE = 'https://api.stripe.com/v1';

    public function issueOnboardingLink(Kindergarten $kindergarten, string $returnUrl, string $refreshUrl): array
    {
        $stripeAccountId = $this->resolveOrCreateStripeAccountId($kindergarten);

        $accountLink = $this->post('/account_links', [
            'account' => $stripeAccountId,
            'type' => 'account_onboarding',
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
        ]);

        $expiresAt = data_get($accountLink, 'expires_at');
        $onboardingUrl = data_get($accountLink, 'url');

        if (! is_string($onboardingUrl) || trim($onboardingUrl) === '') {
            throw new RuntimeException('Stripe account link response missing url');
        }

        if (! is_numeric($expiresAt)) {
            throw new RuntimeException('Stripe account link response missing expires_at');
        }

        return [
            'onboarding_url' => $onboardingUrl,
            'stripe_account_id' => $stripeAccountId,
            'expires_at' => Carbon::createFromTimestampUTC((int) $expiresAt)->toIso8601String(),
        ];
    }

    public function getStatus(Kindergarten $kindergarten): array
    {
        if ($kindergarten->stripe_account_id === null) {
            return [
                'stripe_account_id' => null,
                'charges_enabled' => false,
                'payouts_enabled' => false,
                'onboarding_completed_at' => null,
                'requirements_due' => [],
            ];
        }

        $account = $this->get('/accounts/'.$kindergarten->stripe_account_id);
        $requirementsDue = data_get($account, 'requirements.currently_due', []);

        return [
            'stripe_account_id' => $kindergarten->stripe_account_id,
            'charges_enabled' => (bool) data_get($account, 'charges_enabled', false),
            'payouts_enabled' => (bool) data_get($account, 'payouts_enabled', false),
            'onboarding_completed_at' => $kindergarten->stripe_onboarding_completed_at?->toIso8601String(),
            'requirements_due' => is_array($requirementsDue)
                ? array_values(array_map(static fn (mixed $requirement): string => (string) $requirement, $requirementsDue))
                : [],
        ];
    }

    public function getSalesAvailability(Kindergarten $kindergarten): array
    {
        if ($kindergarten->stripe_account_id === null) {
            return $this->disabledAvailability('STRIPE_ONBOARDING_INCOMPLETE', 'Stripe onboarding is not completed');
        }

        $account = $this->get('/accounts/'.$kindergarten->stripe_account_id);

        if (! (bool) data_get($account, 'charges_enabled', false)) {
            return $this->disabledAvailability('STRIPE_CHARGES_DISABLED', 'Stripe charges are not enabled');
        }

        if ($kindergarten->stripe_onboarding_completed_at === null) {
            return $this->disabledAvailability('STRIPE_ONBOARDING_INCOMPLETE', 'Stripe onboarding is not completed');
        }

        return [
            'sales_enabled' => true,
            'reason_code' => null,
            'reason_message' => null,
        ];
    }

    public function handleAccountUpdatedWebhook(string $payload, string $signatureHeader): void
    {
        $this->verifyWebhookSignature($payload, $signatureHeader);

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Stripe webhook payload is invalid');
        }

        if (! is_array($event) || ($event['type'] ?? null) !== 'account.updated') {
            return;
        }

        $account = data_get($event, 'data.object');

        if (! is_array($account)) {
            return;
        }

        $stripeAccountId = data_get($account, 'id');

        if (! is_string($stripeAccountId) || trim($stripeAccountId) === '') {
            return;
        }

        $kindergarten = Kindergarten::query()->where('stripe_account_id', $stripeAccountId)->first();

        if ($kindergarten === null) {
            return;
        }

        $chargesEnabled = (bool) data_get($account, 'charges_enabled', false);

        if ($chargesEnabled) {
            if ($kindergarten->stripe_onboarding_completed_at === null) {
                $kindergarten->forceFill([
                    'stripe_onboarding_completed_at' => now(),
                ])->save();
            }

            return;
        }

        $kindergarten->forceFill([
            'stripe_onboarding_completed_at' => null,
        ])->save();
    }

    private function resolveOrCreateStripeAccountId(Kindergarten $kindergarten): string
    {
        $this->ensureAccountCreationKeyIsPersisted($kindergarten->id);

        return DB::transaction(function () use ($kindergarten): string {
            $lockedKindergarten = Kindergarten::query()
                ->whereKey($kindergarten->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedKindergarten instanceof Kindergarten) {
                throw new RuntimeException('Kindergarten not found');
            }

            $existingStripeAccountId = $lockedKindergarten->stripe_account_id;

            if (is_string($existingStripeAccountId) && trim($existingStripeAccountId) !== '') {
                return $existingStripeAccountId;
            }

            $idempotencyKey = $lockedKindergarten->stripe_account_creation_idempotency_key;

            if (! is_string($idempotencyKey) || trim($idempotencyKey) === '') {
                throw new RuntimeException('Stripe account creation key is not configured');
            }

            $createdStripeAccountId = $this->createConnectedAccount($idempotencyKey);

            $lockedKindergarten->refresh();

            if (is_string($lockedKindergarten->stripe_account_id) && trim($lockedKindergarten->stripe_account_id) !== '') {
                return $lockedKindergarten->stripe_account_id;
            }

            $lockedKindergarten->forceFill([
                'stripe_account_id' => $createdStripeAccountId,
            ])->save();

            return $createdStripeAccountId;
        });
    }

    private function ensureAccountCreationKeyIsPersisted(string $kindergartenId): void
    {
        DB::transaction(function () use ($kindergartenId): void {
            $lockedKindergarten = Kindergarten::query()
                ->whereKey($kindergartenId)
                ->lockForUpdate()
                ->first();

            if (! $lockedKindergarten instanceof Kindergarten) {
                throw new RuntimeException('Kindergarten not found');
            }

            if (is_string($lockedKindergarten->stripe_account_id) && trim($lockedKindergarten->stripe_account_id) !== '') {
                return;
            }

            if (is_string($lockedKindergarten->stripe_account_creation_idempotency_key)
                && trim($lockedKindergarten->stripe_account_creation_idempotency_key) !== '') {
                return;
            }

            $lockedKindergarten->forceFill([
                'stripe_account_creation_idempotency_key' => Str::uuid()->toString(),
            ])->save();
        });
    }

    private function createConnectedAccount(string $idempotencyKey): string
    {
        $account = $this->post('/accounts', [
            'country' => 'JP',
            'type' => 'standard',
            'capabilities[card_payments][requested]' => 'true',
            'capabilities[transfers][requested]' => 'true',
        ], $idempotencyKey);

        $stripeAccountId = data_get($account, 'id');

        if (! is_string($stripeAccountId) || trim($stripeAccountId) === '') {
            throw new RuntimeException('Stripe account response missing id');
        }

        return $stripeAccountId;
    }

    private function get(string $path): array
    {
        $response = Http::withToken($this->resolveSecret())
            ->connectTimeout(5)
            ->timeout(15)
            ->get($this->stripeUrl($path));

        if ($response->failed()) {
            $responseBody = $response->json() ?? [];
            $errorCode = data_get($responseBody, 'error.code');
            $errorCodeText = is_string($errorCode) && trim($errorCode) !== '' ? $errorCode : 'unknown';

            Log::error('Stripe API request failed', [
                'path' => $path,
                'status' => $response->status(),
                'error_code' => $errorCodeText,
            ]);

            throw new RuntimeException(sprintf(
                'Stripe API request failed for path [%s] with status [%d] and error code [%s]',
                $path,
                $response->status(),
                $errorCodeText,
            ));
        }

        return $response->json() ?? [];
    }

    private function post(string $path, array $payload, ?string $idempotencyKey = null): array
    {
        $request = Http::asForm()
            ->withToken($this->resolveSecret())
            ->connectTimeout(5)
            ->timeout(30);

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
            ]);
        }

        $response = $request->post($this->stripeUrl($path), $payload);

        if ($response->failed()) {
            $responseBody = $response->json() ?? [];
            $errorCode = data_get($responseBody, 'error.code');
            $errorCodeText = is_string($errorCode) && trim($errorCode) !== '' ? $errorCode : 'unknown';

            Log::error('Stripe API request failed', [
                'path' => $path,
                'status' => $response->status(),
                'error_code' => $errorCodeText,
            ]);

            throw new RuntimeException(sprintf(
                'Stripe API request failed for path [%s] with status [%d] and error code [%s]',
                $path,
                $response->status(),
                $errorCodeText,
            ));
        }

        return $response->json() ?? [];
    }

    private function resolveSecret(): string
    {
        $secret = config('services.stripe.secret');

        if (! is_string($secret) || trim($secret) === '') {
            throw new RuntimeException('Stripe secret is not configured');
        }

        return $secret;
    }

    private function verifyWebhookSignature(string $payload, string $signatureHeader): void
    {
        $secret = config('services.stripe.webhook_secret');

        if (! is_string($secret) || trim($secret) === '') {
            throw new RuntimeException('Stripe webhook secret is not configured');
        }

        $signatureParts = explode(',', $signatureHeader);
        $timestamp = null;
        $v1Signatures = [];

        foreach ($signatureParts as $part) {
            $pair = explode('=', $part, 2);

            if (count($pair) !== 2) {
                continue;
            }

            [$key, $value] = $pair;

            if ($key === 't' && trim($value) !== '') {
                $timestamp = $value;
            }

            if ($key === 'v1' && trim($value) !== '') {
                $v1Signatures[] = $value;
            }
        }

        if (! is_string($timestamp) || trim($timestamp) === '' || ! is_numeric($timestamp)) {
            throw new RuntimeException('Stripe webhook signature is invalid');
        }

        $timestampValue = (int) $timestamp;
        $now = time();

        if (abs($timestampValue - $now) > 300) {
            throw new RuntimeException('Stripe webhook signature is invalid');
        }

        if ($v1Signatures === []) {
            throw new RuntimeException('Stripe webhook signature is invalid');
        }

        foreach ($v1Signatures as $v1Signature) {
            $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

            if (hash_equals($expectedSignature, $v1Signature)) {
                return;
            }
        }

        throw new RuntimeException('Stripe webhook signature is invalid');
    }

    private function stripeUrl(string $path): string
    {
        return rtrim(self::STRIPE_API_BASE, '/').'/'.ltrim($path, '/');
    }

    private function disabledAvailability(string $reasonCode, string $reasonMessage): array
    {
        return [
            'sales_enabled' => false,
            'reason_code' => $reasonCode,
            'reason_message' => $reasonMessage,
        ];
    }
}
