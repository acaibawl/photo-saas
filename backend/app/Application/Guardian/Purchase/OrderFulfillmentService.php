<?php

namespace App\Application\Guardian\Purchase;

use App\Application\Shared\Exceptions\StripeWebhookValidationException;
use App\Models\Entitlement;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

final class OrderFulfillmentService
{
    public function handleStripeWebhook(string $payload, string $signatureHeader): void
    {
        $this->verifyWebhookSignature($payload, $signatureHeader);

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new StripeWebhookValidationException('Stripe webhook payload is invalid');
        }

        if (! is_array($event) || ($event['type'] ?? null) !== 'checkout.session.completed') {
            return;
        }

        $session = data_get($event, 'data.object');

        if (! is_array($session)) {
            return;
        }

        $orderId = data_get($session, 'client_reference_id');
        $checkoutSessionId = data_get($session, 'id');
        $paymentIntentId = data_get($session, 'payment_intent');

        if (! is_string($orderId) || trim($orderId) === '') {
            if (! is_string($checkoutSessionId) || trim($checkoutSessionId) === '') {
                return;
            }

            $orderId = Order::query()
                ->where('stripe_checkout_session_id', $checkoutSessionId)
                ->value('id');
        }

        if (! is_string($orderId) || trim($orderId) === '') {
            return;
        }

        DB::transaction(function () use ($orderId, $checkoutSessionId, $paymentIntentId): void {
            $order = Order::query()
                ->with('items')
                ->whereKey($orderId)
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Order) {
                return;
            }

            if (! in_array($order->status, ['pending', 'paid'], true)) {
                return;
            }

            $order->forceFill([
                'status' => 'paid',
                'stripe_checkout_session_id' => is_string($checkoutSessionId) && trim($checkoutSessionId) !== ''
                    ? $checkoutSessionId
                    : $order->stripe_checkout_session_id,
                'stripe_payment_intent_id' => is_string($paymentIntentId) && trim($paymentIntentId) !== ''
                    ? $paymentIntentId
                    : $order->stripe_payment_intent_id,
            ])->save();

            foreach ($order->items as $item) {
                Entitlement::query()->firstOrCreate(
                    ['order_item_id' => $item->id],
                    [
                        'guardian_id' => $order->guardian_id,
                        'photo_id' => $item->photo_id,
                        'granted_at' => now(),
                    ],
                );
            }
        });
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
            throw new StripeWebhookValidationException('Stripe webhook signature is invalid');
        }

        $timestampValue = (int) $timestamp;
        if (abs($timestampValue - time()) > 300) {
            throw new StripeWebhookValidationException('Stripe webhook signature is invalid');
        }

        if ($v1Signatures === []) {
            throw new StripeWebhookValidationException('Stripe webhook signature is invalid');
        }

        foreach ($v1Signatures as $v1Signature) {
            $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

            if (hash_equals($expectedSignature, $v1Signature)) {
                return;
            }
        }

        throw new StripeWebhookValidationException('Stripe webhook signature is invalid');
    }
}
