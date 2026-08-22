<?php

namespace App\Application\Guardian\Purchase;

use App\Application\Guardian\Photo\GuardianPhotoService;
use App\Domain\Guardian\Exceptions\CheckoutAmountMismatchException;
use App\Domain\Guardian\Exceptions\EntitlementNotFoundException;
use App\Domain\Guardian\Exceptions\OrderAlreadyPaidOrClosedException;
use App\Domain\Guardian\Exceptions\PhotoPurchaseNotAllowedException;
use App\Domain\Guardian\Exceptions\SalesDisabledForKindergartenException;
use App\Models\Entitlement;
use App\Models\Guardian;
use App\Models\Kindergarten;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Uri;
use RuntimeException;

final class GuardianPurchaseService
{
    public function __construct(
        private readonly GuardianPhotoService $photoService,
        private readonly OrderFulfillmentService $orderFulfillmentService,
    ) {}

    /**
     * @param  array<int, string>  $photoIds
     */
    public function createCheckoutSession(
        Guardian $guardian,
        array $photoIds,
        int $checkoutAmount,
        string $successUrl,
        string $cancelUrl,
    ): array {
        $uniquePhotoIds = array_values(array_unique(array_map(static fn (mixed $photoId): string => (string) $photoId, $photoIds)));

        // 同じ写真へのpending注文があれば、Stripe通信をDBロック外で行い先に解消しておく
        $this->resolvePendingOrdersBlockingPhotos($guardian, $uniquePhotoIds);

        $pendingCheckout = DB::transaction(function () use ($guardian, $uniquePhotoIds, $checkoutAmount): array {
            Guardian::query()
                ->whereKey($guardian->id)
                ->lockForUpdate()
                ->first();

            $photos = $this->resolvePurchasablePhotos($guardian, $uniquePhotoIds);

            if ($photos->count() !== count($uniquePhotoIds)) {
                throw new PhotoPurchaseNotAllowedException;
            }

            $kindergartenIds = $photos->pluck('kindergarten_id')->unique();
            if ($kindergartenIds->count() !== 1) {
                throw new PhotoPurchaseNotAllowedException;
            }

            $kindergarten = Kindergarten::query()->find($kindergartenIds->first());
            if (! $kindergarten instanceof Kindergarten) {
                throw new PhotoPurchaseNotAllowedException;
            }

            if ($kindergarten->stripe_account_id === null || $kindergarten->stripe_onboarding_completed_at === null) {
                throw new SalesDisabledForKindergartenException;
            }

            if (Entitlement::query()
                ->where('guardian_id', $guardian->id)
                ->whereIn('photo_id', $uniquePhotoIds)
                ->exists()) {
                throw new OrderAlreadyPaidOrClosedException;
            }

            if (OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.guardian_id', $guardian->id)
                ->where('orders.status', 'pending')
                ->whereIn('order_items.photo_id', $uniquePhotoIds)
                ->exists()) {
                throw new OrderAlreadyPaidOrClosedException;
            }

            $totalAmount = (int) $photos->sum('price');

            if ($totalAmount !== $checkoutAmount) {
                throw new CheckoutAmountMismatchException;
            }

            /** @var EloquentCollection<int, Photo> $photos */
            $platformFeeAmount = $this->calculatePlatformFeeAmount($totalAmount);

            $order = Order::create([
                'guardian_id' => $guardian->id,
                'kindergarten_id' => $kindergarten->id,
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'platform_fee_amount' => $platformFeeAmount,
            ]);

            foreach ($photos as $photo) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'photo_id' => $photo->id,
                    'price' => $photo->price,
                ]);
            }

            return [
                'order' => $order,
                'kindergarten' => $kindergarten,
                'photos' => $photos,
                'total_amount' => $totalAmount,
                'platform_fee_amount' => $platformFeeAmount,
            ];
        });

        /** @var Order $order */
        $order = $pendingCheckout['order'];
        /** @var Kindergarten $kindergarten */
        $kindergarten = $pendingCheckout['kindergarten'];
        /** @var EloquentCollection<int, Photo> $photos */
        $photos = $pendingCheckout['photos'];
        $totalAmount = $pendingCheckout['total_amount'];
        $platformFeeAmount = $pendingCheckout['platform_fee_amount'];

        try {
            $checkoutSession = $this->createStripeCheckoutSession(
                order: $order,
                kindergarten: $kindergarten,
                photos: $photos,
                successUrl: $this->withCheckoutOrderId($successUrl, $order->id),
                cancelUrl: $this->withCheckoutOrderId($cancelUrl, $order->id),
                platformFeeAmount: $platformFeeAmount,
            );

            $order->forceFill([
                'stripe_checkout_session_id' => $checkoutSession['id'],
            ])->save();
        } catch (RuntimeException $exception) {
            $this->markOrderAsFailed($order);

            throw $exception;
        }

        return [
            'order_id' => $order->id,
            'checkout_session_id' => $checkoutSession['id'],
            'checkout_url' => $checkoutSession['url'],
            'total_amount' => $totalAmount,
            'currency' => 'jpy',
        ];
    }

    public function listOrders(
        Guardian $guardian,
        ?string $status,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Order::query()
            ->with(['items.photo.album'])
            ->where('guardian_id', $guardian->id)
            ->orderByDesc('created_at');

        if ($status !== null && trim($status) !== '') {
            $query->where('status', $status);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function syncOrderFromStripeCheckoutSession(Guardian $guardian, string $orderId): ?Order
    {
        $order = Order::query()
            ->with(['items.photo.album'])
            ->where('guardian_id', $guardian->id)
            ->whereKey($orderId)
            ->first();

        if (! $order instanceof Order) {
            return null;
        }

        return $this->syncPendingOrderWithStripe($order);
    }

    /**
     * Stripe側の最新状態をOrderへ反映する。Webhook到達待ちの間や到達漏れ時の補完に使う
     */
    public function syncPendingOrderWithStripe(Order $order): Order
    {
        if ($order->status !== 'pending' || ! is_string($order->stripe_checkout_session_id) || trim($order->stripe_checkout_session_id) === '') {
            return $order;
        }

        $checkoutSession = $this->retrieveStripeCheckoutSession($order->stripe_checkout_session_id);
        $checkoutOrderId = data_get($checkoutSession, 'client_reference_id');

        if ($checkoutOrderId !== $order->id) {
            return $order;
        }

        if (data_get($checkoutSession, 'payment_status') === 'paid') {
            $this->orderFulfillmentService->fulfillPaidCheckoutSession(
                $order->id,
                data_get($checkoutSession, 'id'),
                data_get($checkoutSession, 'payment_intent'),
            );
        } elseif (data_get($checkoutSession, 'status') === 'expired') {
            $this->markOrderAsFailed($order);
        }

        return Order::query()
            ->with(['items.photo.album'])
            ->whereKey($order->id)
            ->first() ?? $order;
    }

    public function cancelCheckoutSession(Guardian $guardian, string $orderId): ?Order
    {
        $order = Order::query()
            ->with(['items.photo.album'])
            ->where('guardian_id', $guardian->id)
            ->whereKey($orderId)
            ->first();

        if (! $order instanceof Order) {
            return null;
        }

        $this->forceResolvePendingOrder($order);

        return Order::query()
            ->with(['items.photo.album'])
            ->whereKey($order->id)
            ->first();
    }

    /**
     * pending注文をStripe側と強制的に確定させる。open状態なら即座にexpireを要求する
     */
    private function forceResolvePendingOrder(Order $order): void
    {
        if ($order->status !== 'pending' || ! is_string($order->stripe_checkout_session_id) || trim($order->stripe_checkout_session_id) === '') {
            return;
        }

        $checkoutSession = $this->retrieveStripeCheckoutSession($order->stripe_checkout_session_id);
        if (data_get($checkoutSession, 'client_reference_id') !== $order->id) {
            return;
        }

        if (data_get($checkoutSession, 'payment_status') === 'paid') {
            $this->orderFulfillmentService->fulfillPaidCheckoutSession(
                $order->id,
                data_get($checkoutSession, 'id'),
                data_get($checkoutSession, 'payment_intent'),
            );
        } elseif (data_get($checkoutSession, 'status') === 'open') {
            $expiredSession = $this->expireStripeCheckoutSession($order->stripe_checkout_session_id);

            if (data_get($expiredSession, 'status') === 'expired') {
                $this->markOrderAsFailed($order);
            }
        } elseif (data_get($checkoutSession, 'status') === 'expired') {
            $this->markOrderAsFailed($order);
        }
    }

    public function listPurchasedPhotos(
        Guardian $guardian,
        ?string $albumId,
        ?string $eventDateFrom,
        ?string $eventDateTo,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Entitlement::query()
            ->with(['photo.album'])
            ->where('guardian_id', $guardian->id)
            ->orderByDesc('granted_at');

        if ($albumId !== null && trim($albumId) !== '') {
            $query->whereHas('photo', function ($photoQuery) use ($albumId): void {
                $photoQuery->where('album_id', $albumId);
            });
        }

        if ($eventDateFrom !== null && trim($eventDateFrom) !== '') {
            $query->whereHas('photo.album', function ($albumQuery) use ($eventDateFrom): void {
                $albumQuery->whereDate('event_date', '>=', $eventDateFrom);
            });
        }

        if ($eventDateTo !== null && trim($eventDateTo) !== '') {
            $query->whereHas('photo.album', function ($albumQuery) use ($eventDateTo): void {
                $albumQuery->whereDate('event_date', '<=', $eventDateTo);
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function downloadUrl(Guardian $guardian, string $photoId): array
    {
        $entitlement = Entitlement::query()
            ->with('photo')
            ->where('guardian_id', $guardian->id)
            ->where('photo_id', $photoId)
            ->first();

        $storagePath = $entitlement instanceof Entitlement ? data_get($entitlement, 'photo.storage_path') : null;

        if (! $entitlement instanceof Entitlement || $storagePath === null || $storagePath === '') {
            throw new EntitlementNotFoundException;
        }

        $expiresAt = now()->addMinutes(10);
        /** @var FilesystemAdapter $filesystem */
        $filesystem = Storage::disk('s3');

        return [
            'download_url' => $filesystem->temporaryUrl($storagePath, $expiresAt),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function previewUrlForPhoto(?string $path): ?string
    {
        return $this->photoService->previewUrlFor($path);
    }

    private function calculatePlatformFeeAmount(int $totalAmount): int
    {
        $rate = (float) config('purchase.platform_fee_rate');
        $minimumAmount = (int) config('purchase.platform_fee_min_amount');
        $maximumAmount = (int) config('purchase.platform_fee_max_amount');

        if ($minimumAmount > $maximumAmount) {
            [$minimumAmount, $maximumAmount] = [$maximumAmount, $minimumAmount];
        }

        $calculatedAmount = (int) round($totalAmount * $rate);
        $boundedAmount = max($minimumAmount, min($calculatedAmount, $maximumAmount));

        return min($boundedAmount, $totalAmount);
    }

    private function markOrderAsFailed(Order $order): void
    {
        Order::query()
            ->whereKey($order->id)
            ->where('status', 'pending')
            ->update(['status' => 'failed']);
    }

    /**
     * @param  array<int, string>  $photoIds
     */
    private function resolvePendingOrdersBlockingPhotos(Guardian $guardian, array $photoIds): void
    {
        if ($photoIds === []) {
            return;
        }

        $blockingOrderIds = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.guardian_id', $guardian->id)
            ->where('orders.status', 'pending')
            ->whereIn('order_items.photo_id', $photoIds)
            ->pluck('orders.id')
            ->unique();

        if ($blockingOrderIds->isEmpty()) {
            return;
        }

        $blockingOrders = Order::query()->whereIn('id', $blockingOrderIds)->get();

        foreach ($blockingOrders as $blockingOrder) {
            try {
                $this->forceResolvePendingOrder($blockingOrder);
            } catch (\Throwable $exception) {
                Log::warning('Failed to resolve pending order blocking repurchase', [
                    'order_id' => $blockingOrder->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function withCheckoutOrderId(string $url, string $orderId): string
    {
        return Uri::of($url)->withQuery(['order_id' => $orderId])->value();
    }

    private function checkoutSessionExpiresAt(): int
    {
        // Stripeは30分〜24時間の範囲のみ許容する
        $ttlMinutes = min(max((int) config('purchase.checkout_session_ttl_minutes'), 30), 1440);

        return now()->addMinutes($ttlMinutes)->timestamp;
    }

    private function retrieveStripeCheckoutSession(string $checkoutSessionId): array
    {
        $secret = config('services.stripe.secret');

        if (! is_string($secret) || trim($secret) === '') {
            throw new RuntimeException('Stripe secret is not configured');
        }

        $response = Http::withToken($secret)
            ->connectTimeout(5)
            ->timeout(30)
            ->get('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($checkoutSessionId));

        if ($response->failed()) {
            Log::error('Stripe API request failed', $this->stripeFailureLogContext($response, '/v1/checkout/sessions/'.$checkoutSessionId));

            throw new RuntimeException('Stripe checkout session retrieval failed');
        }

        return $response->json() ?? [];
    }

    private function expireStripeCheckoutSession(string $checkoutSessionId): array
    {
        $secret = config('services.stripe.secret');

        if (! is_string($secret) || trim($secret) === '') {
            throw new RuntimeException('Stripe secret is not configured');
        }

        $response = Http::asForm()
            ->withToken($secret)
            ->connectTimeout(5)
            ->timeout(30)
            ->post('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($checkoutSessionId).'/expire');

        if ($response->failed()) {
            Log::error('Stripe API request failed', $this->stripeFailureLogContext($response, '/v1/checkout/sessions/'.$checkoutSessionId.'/expire'));

            throw new RuntimeException('Stripe checkout session expiration failed');
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<int, string>  $photoIds
     * @return EloquentCollection<int, Photo>
     */
    private function resolvePurchasablePhotos(Guardian $guardian, array $photoIds): EloquentCollection
    {
        if ($photoIds === []) {
            return new EloquentCollection;
        }

        $photoOrder = array_flip($photoIds);

        return Photo::query()
            ->select('photos.*')
            ->distinct()
            ->join('photo_child_tags', 'photo_child_tags.photo_id', '=', 'photos.id')
            ->join('guardian_child', function ($join) use ($guardian): void {
                $join->on('guardian_child.child_id', '=', 'photo_child_tags.child_id')
                    ->where('guardian_child.guardian_id', '=', $guardian->id)
                    ->whereNull('guardian_child.unlinked_at');
            })
            ->whereIn('photos.id', $photoIds)
            ->whereNotNull('photos.price')
            ->where('photos.preview_status', 'ready')
            ->get()
            ->sortBy(static fn (Photo $photo): int => $photoOrder[$photo->id] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * @param  EloquentCollection<int, Photo>  $photos
     * @return array{id: string, url: string}
     */
    private function createStripeCheckoutSession(
        Order $order,
        Kindergarten $kindergarten,
        EloquentCollection $photos,
        string $successUrl,
        string $cancelUrl,
        int $platformFeeAmount,
    ): array {
        $secret = config('services.stripe.secret');

        if (! is_string($secret) || trim($secret) === '') {
            throw new RuntimeException('Stripe secret is not configured');
        }

        $lineItems = $photos->map(static fn (Photo $photo): array => [
            'quantity' => 1,
            'price_data' => [
                'currency' => 'jpy',
                'unit_amount' => $photo->price,
                'product_data' => [
                    'name' => 'Photo '.$photo->id,
                ],
            ],
        ])->values()->all();

        $response = Http::asForm()
            ->withToken($secret)
            ->connectTimeout(5)
            ->timeout(30)
            ->withHeaders([
                'Idempotency-Key' => 'guardian-order-'.$order->id,
            ])
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => $order->id,
                'expires_at' => $this->checkoutSessionExpiresAt(),
                'metadata' => [
                    'order_id' => $order->id,
                    'guardian_id' => $order->guardian_id,
                    'kindergarten_id' => $kindergarten->id,
                ],
                'line_items' => $lineItems,
                'payment_intent_data' => [
                    'application_fee_amount' => $platformFeeAmount,
                    'transfer_data' => [
                        'destination' => $kindergarten->stripe_account_id,
                    ],
                    'metadata' => [
                        'order_id' => $order->id,
                    ],
                ],
            ]);

        if ($response->failed()) {
            Log::error('Stripe API request failed', $this->stripeFailureLogContext($response, '/v1/checkout/sessions'));

            throw new RuntimeException('Stripe checkout session creation failed');
        }

        $payload = $response->json() ?? [];
        $checkoutSessionId = data_get($payload, 'id');
        $checkoutUrl = data_get($payload, 'url');

        if (! is_string($checkoutSessionId) || trim($checkoutSessionId) === '' || ! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            throw new RuntimeException('Stripe checkout session response is invalid');
        }

        return [
            'id' => $checkoutSessionId,
            'url' => $checkoutUrl,
        ];
    }

    /**
     * @return array{path: string, status: int, error_code: string, request_id?: string}
     */
    private function stripeFailureLogContext(Response $response, string $path): array
    {
        $responseBody = $response->json() ?? [];
        $errorCode = data_get($responseBody, 'error.code');
        $errorCodeText = is_string($errorCode) && trim($errorCode) !== '' ? $errorCode : 'unknown';

        $context = [
            'path' => $path,
            'status' => $response->status(),
            'error_code' => $errorCodeText,
        ];

        $requestId = $response->header('Request-Id');
        if (trim($requestId) !== '') {
            $context['request_id'] = $requestId;
        }

        return $context;
    }
}
