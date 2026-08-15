<?php

namespace App\Application\Kindergarten;

use App\Models\Kindergarten;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

final class StripeConnectService
{
    private const STRIPE_API_BASE = 'https://api.stripe.com/v1';

    private ?string $accountCreationIdempotencyKey = null;

    public function issueOnboardingLink(Kindergarten $kindergarten, string $returnUrl, string $refreshUrl): array
    {
        $stripeAccountId = $kindergarten->stripe_account_id;

        if ($stripeAccountId === null) {
            $stripeAccountId = $this->createConnectedAccount();

            $kindergarten->forceFill([
                'stripe_account_id' => $stripeAccountId,
            ])->save();
        }

        $accountLink = $this->post('/account_links', [
            'account' => $stripeAccountId,
            'type' => 'account_onboarding',
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
        ]);

        $expiresAt = data_get($accountLink, 'expires_at');

        if (! is_numeric($expiresAt)) {
            throw new RuntimeException('Stripe account link response missing expires_at');
        }

        return [
            'onboarding_url' => (string) data_get($accountLink, 'url', ''),
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

        $kindergarten->forceFill([
            'stripe_onboarding_completed_at' => (bool) data_get($account, 'charges_enabled', false) ? now() : null,
        ])->save();
    }

    private function createConnectedAccount(): string
    {
        $idempotencyKey = $this->accountCreationIdempotencyKey ??= Str::uuid()->toString();

        $account = $this->post('/accounts', [
            'country' => 'JP',
            'type' => 'express',
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
            throw new RuntimeException('Stripe API request failed');
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
            throw new RuntimeException('Stripe API request failed');
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
        $v1Signature = null;

        foreach ($signatureParts as $part) {
            $pair = explode('=', $part, 2);

            if (count($pair) !== 2) {
                continue;
            }

            [$key, $value] = $pair;

            if ($key === 't') {
                $timestamp = $value;
            }

            if ($key === 'v1') {
                $v1Signature = $value;
            }
        }

        if (! is_string($timestamp) || trim($timestamp) === '' || ! is_string($v1Signature) || trim($v1Signature) === '') {
            throw new RuntimeException('Stripe webhook signature is invalid');
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        if (! hash_equals($expectedSignature, $v1Signature)) {
            throw new RuntimeException('Stripe webhook signature is invalid');
        }
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
