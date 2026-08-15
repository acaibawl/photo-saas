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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class GuardianPurchaseService
{
    public function __construct(private readonly GuardianPhotoService $photoService) {}

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
                successUrl: $successUrl,
                cancelUrl: $cancelUrl,
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
            $responseBody = $response->json() ?? [];
            $errorCode = data_get($responseBody, 'error.code');
            $errorCodeText = is_string($errorCode) && trim($errorCode) !== '' ? $errorCode : 'unknown';

            Log::error('Stripe API request failed', [
                'path' => '/v1/checkout/sessions',
                'status' => $response->status(),
                'error_code' => $errorCodeText,
                'response_body' => $responseBody,
            ]);

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
}
