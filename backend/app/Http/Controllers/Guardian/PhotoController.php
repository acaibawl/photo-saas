<?php

namespace App\Http\Controllers\Guardian;

use App\Application\Guardian\Photo\GuardianPhotoService;
use App\Application\Guardian\Purchase\GuardianPurchaseService;
use App\Domain\Guardian\Exceptions\EntitlementNotFoundException;
use App\Domain\Photo\Exceptions\PhotoAccessDeniedException;
use App\Domain\Photo\Exceptions\PhotoNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guardian\ListGuardianAlbumsRequest;
use App\Http\Requests\Guardian\ListGuardianPhotosRequest;
use App\Models\Guardian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function albums(ListGuardianAlbumsRequest $request, GuardianPhotoService $service): JsonResponse
    {
        $guardian = $request->user('guardian');

        if (! $guardian instanceof Guardian) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'GUARDIAN_AUTH_REQUIRED',
            ], 401);
        }

        try {
            return response()->json($service->listAlbums($guardian, $request->string('child_id')->toString() !== '' ? $request->string('child_id')->toString() : null));
        } catch (PhotoAccessDeniedException) {
            return response()->json([
                'message' => 'Photo access denied',
                'code' => 'PHOTO_ACCESS_DENIED',
            ], 403);
        }
    }

    public function index(ListGuardianPhotosRequest $request, GuardianPhotoService $service): JsonResponse
    {
        $guardian = $request->user('guardian');

        if (! $guardian instanceof Guardian) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'GUARDIAN_AUTH_REQUIRED',
            ], 401);
        }

        try {
            $visibleChildIds = $service->activeChildIds($guardian);

            $paginator = $service->listPhotos(
                $guardian,
                $request->string('child_id')->toString() !== '' ? $request->string('child_id')->toString() : null,
                $request->string('album_id')->toString() !== '' ? $request->string('album_id')->toString() : null,
                $request->string('event_date_from')->toString() !== '' ? $request->string('event_date_from')->toString() : null,
                $request->string('event_date_to')->toString() !== '' ? $request->string('event_date_to')->toString() : null,
                $request->integer('page', 1),
                $request->integer('per_page', 20),
            );
            $purchasedPhotoIds = $service->purchasedPhotoIds(
                $guardian,
                collect($paginator->items())->pluck('id')->all(),
            );

            return response()->json([
                'data' => array_map(fn ($photo): array => [
                    'photo_id' => $photo->id,
                    'album_id' => $photo->album_id,
                    'price' => $photo->price,
                    'purchased' => $purchasedPhotoIds->contains($photo->id),
                    'preview_url' => $service->previewUrlFor($photo->preview_path),
                    'event_date' => $photo->album?->event_date?->toDateString(),
                    'tagged_child_ids' => $photo->taggedChildren
                        ->pluck('id')
                        ->filter(fn ($childId): bool => $visibleChildIds->contains($childId))
                        ->values()
                        ->all(),
                ], $paginator->items()),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (PhotoAccessDeniedException) {
            return response()->json([
                'message' => 'Photo access denied',
                'code' => 'PHOTO_ACCESS_DENIED',
            ], 403);
        }
    }

    public function show(Request $request, string $photoId, GuardianPhotoService $service): JsonResponse
    {
        $guardian = $request->user('guardian');

        if (! $guardian instanceof Guardian) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'GUARDIAN_AUTH_REQUIRED',
            ], 401);
        }

        try {
            return response()->json($service->findPhotoDetail($guardian, $photoId));
        } catch (PhotoNotFoundException) {
            return response()->json([
                'message' => 'Photo not found',
                'code' => 'PHOTO_NOT_FOUND',
            ], 404);
        } catch (PhotoAccessDeniedException) {
            return response()->json([
                'message' => 'Photo access denied',
                'code' => 'PHOTO_ACCESS_DENIED',
            ], 403);
        }
    }

    public function refreshPreviewUrl(Request $request, string $photoId, GuardianPhotoService $service): JsonResponse
    {
        $guardian = $request->user('guardian');

        if (! $guardian instanceof Guardian) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'GUARDIAN_AUTH_REQUIRED',
            ], 401);
        }

        try {
            return response()->json($service->refreshPreviewUrl($guardian, $photoId));
        } catch (PhotoNotFoundException) {
            return response()->json([
                'message' => 'Photo not found',
                'code' => 'PHOTO_NOT_FOUND',
            ], 404);
        } catch (PhotoAccessDeniedException) {
            return response()->json([
                'message' => 'Photo access denied',
                'code' => 'PHOTO_ACCESS_DENIED',
            ], 403);
        }
    }

    public function downloadUrl(Request $request, string $photoId, GuardianPurchaseService $service): JsonResponse
    {
        $guardian = $request->user('guardian');

        if (! $guardian instanceof Guardian) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'GUARDIAN_AUTH_REQUIRED',
            ], 401);
        }

        try {
            return response()->json($service->downloadUrl($guardian, $photoId));
        } catch (EntitlementNotFoundException) {
            return response()->json([
                'message' => 'Entitlement not found',
                'code' => 'ENTITLEMENT_NOT_FOUND',
            ], 404);
        }
    }
}
