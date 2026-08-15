<?php

namespace App\Http\Controllers\Guardian;

use App\Application\Guardian\Purchase\GuardianPurchaseService;
use App\Domain\Guardian\Exceptions\CheckoutAmountMismatchException;
use App\Domain\Guardian\Exceptions\OrderAlreadyPaidOrClosedException;
use App\Domain\Guardian\Exceptions\PhotoPurchaseNotAllowedException;
use App\Domain\Guardian\Exceptions\SalesDisabledForKindergartenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guardian\CreateGuardianCheckoutSessionRequest;
use App\Http\Requests\Guardian\ListGuardianOrdersRequest;
use App\Http\Requests\Guardian\ListPurchasedPhotosRequest;
use App\Models\Guardian;
use Illuminate\Http\JsonResponse;

class PurchaseController extends Controller
{
    public function checkoutSession(CreateGuardianCheckoutSessionRequest $request, GuardianPurchaseService $service): JsonResponse
    {
        $guardian = $request->user('guardian');

        if (! $guardian instanceof Guardian) {
            return $this->unauthenticatedResponse();
        }

        try {
            return response()->json($service->createCheckoutSession(
                $guardian,
                $request->input('photo_ids', []),
                $request->integer('checkout_amount'),
                $request->string('success_url')->toString(),
                $request->string('cancel_url')->toString(),
            ));
        } catch (PhotoPurchaseNotAllowedException) {
            return $this->errorResponse(403, 'PHOTO_PURCHASE_NOT_ALLOWED', 'Photo purchase not allowed');
        } catch (SalesDisabledForKindergartenException) {
            return $this->errorResponse(403, 'SALES_DISABLED_FOR_KINDERGARTEN', 'Sales disabled for kindergarten');
        } catch (CheckoutAmountMismatchException) {
            return $this->errorResponse(409, 'CHECKOUT_AMOUNT_MISMATCH', 'Checkout amount mismatch');
        } catch (OrderAlreadyPaidOrClosedException) {
            return $this->errorResponse(409, 'ORDER_ALREADY_PAID_OR_CLOSED', 'Order already paid or closed');
        }
    }

    public function orders(ListGuardianOrdersRequest $request, GuardianPurchaseService $service): JsonResponse
    {
        $guardian = $request->user('guardian');

        if (! $guardian instanceof Guardian) {
            return $this->unauthenticatedResponse();
        }

        $paginator = $service->listOrders(
            $guardian,
            $request->filled('status') ? $request->string('status')->toString() : null,
            $request->integer('page', 1),
            $request->integer('per_page', 20),
        );

        return response()->json([
            'data' => collect($paginator->items())->map(static fn ($order): array => [
                'order_id' => $order->id,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $order->items->map(static fn ($item): array => [
                    'order_item_id' => $item->id,
                    'photo_id' => $item->photo_id,
                    'price' => $item->price,
                ])->values()->all(),
            ])->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function purchasedPhotos(ListPurchasedPhotosRequest $request, GuardianPurchaseService $service): JsonResponse
    {
        $guardian = $request->user('guardian');

        if (! $guardian instanceof Guardian) {
            return $this->unauthenticatedResponse();
        }

        $paginator = $service->listPurchasedPhotos(
            $guardian,
            $request->filled('album_id') ? $request->string('album_id')->toString() : null,
            $request->filled('event_date_from') ? $request->string('event_date_from')->toString() : null,
            $request->filled('event_date_to') ? $request->string('event_date_to')->toString() : null,
            $request->integer('page', 1),
            $request->integer('per_page', 20),
        );

        return response()->json([
            'data' => collect($paginator->items())->map(static fn ($entitlement) => [
                'photo_id' => $entitlement->photo_id,
                'album_id' => $entitlement->photo?->album_id,
                'downloadable' => true,
                'purchased_at' => $entitlement->granted_at?->toIso8601String(),
                'event_date' => $entitlement->photo?->album?->event_date?->toDateString(),
                'preview_url' => $service->previewUrlForPhoto($entitlement->photo?->preview_path),
            ])->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function unauthenticatedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Unauthenticated',
            'code' => 'GUARDIAN_AUTH_REQUIRED',
        ], 401);
    }

    private function errorResponse(int $status, string $code, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
        ], $status);
    }
}
