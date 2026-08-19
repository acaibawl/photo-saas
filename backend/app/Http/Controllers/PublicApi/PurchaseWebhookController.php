<?php

namespace App\Http\Controllers\PublicApi;

use App\Application\Guardian\Purchase\OrderFulfillmentService;
use App\Application\Kindergarten\StripeConnectService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PurchaseWebhookController extends Controller
{
    public function stripe(
        Request $request,
        OrderFulfillmentService $orderFulfillmentService,
        StripeConnectService $stripeConnectService,
    ): JsonResponse {
        $payload = $request->getContent();
        $signatureHeader = (string) $request->header('Stripe-Signature', '');

        try {
            // 決済完了イベント（checkout.session.completed）を処理する。対象外のイベントは内部で無視される。
            $orderFulfillmentService->handleStripeWebhook($payload, $signatureHeader);

            // Connectアカウントの状態更新イベント（account.updated）を処理する。対象外のイベントは内部で無視される。
            $stripeConnectService->handleAccountUpdatedWebhook($payload, $signatureHeader);
        } catch (RuntimeException) {
            return response()->json([
                'message' => 'Stripe webhook error',
                'code' => 'STRIPE_WEBHOOK_ERROR',
            ], 400);
        }

        return response()->json(['received' => true]);
    }
}
