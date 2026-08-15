<?php

namespace App\Http\Controllers\PublicApi;

use App\Application\Guardian\Purchase\OrderFulfillmentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PurchaseWebhookController extends Controller
{
    public function stripe(Request $request, OrderFulfillmentService $service): JsonResponse
    {
        try {
            $service->handleStripeWebhook(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
            );
        } catch (RuntimeException) {
            return response()->json([
                'message' => 'Stripe webhook error',
                'code' => 'STRIPE_WEBHOOK_ERROR',
            ], 400);
        }

        return response()->json(['received' => true]);
    }
}
