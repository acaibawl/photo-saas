<?php

namespace App\Http\Controllers\Staff;

use App\Application\Staff\Photo\PhotoManagementService;
use App\Domain\Photo\PhotoPreviewStatus;
use App\Domain\Photo\PhotoPriceStatus;
use App\Domain\Album\Exceptions\AlbumNotFoundException;
use App\Domain\Album\Exceptions\AlbumTenantScopeViolationException;
use App\Domain\Child\Exceptions\ChildTenantScopeViolationException;
use App\Domain\Photo\Exceptions\PhotoNotFoundException;
use App\Domain\Photo\Exceptions\PhotoNotReadyForUpdateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ListPhotosRequest;
use App\Http\Requests\Staff\UpdatePhotoRequest;
use App\Http\Requests\Staff\UploadPhotoBatchRequest;
use App\Models\KindergartenStaff;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function priceStatuses(Request $request): JsonResponse
    {
        if ($this->resolveAuthenticatedStaff($request) === null) {
            return $this->unauthenticatedResponse();
        }

        return response()->json([
            'data' => array_map(
                fn (PhotoPriceStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                PhotoPriceStatus::cases(),
            ),
        ]);
    }

    public function previewStatuses(Request $request): JsonResponse
    {
        if ($this->resolveAuthenticatedStaff($request) === null) {
            return $this->unauthenticatedResponse();
        }

        return response()->json([
            'data' => array_map(
                fn (PhotoPreviewStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                PhotoPreviewStatus::cases(),
            ),
        ]);
    }

    public function uploadBatch(UploadPhotoBatchRequest $request, PhotoManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            return response()->json($service->acceptUploadBatch(
                $staff,
                $request->filled('album_id') ? $request->string('album_id')->toString() : null,
                $request->file('files', []),
                $request->input('price') !== null ? $request->integer('price') : null,
                $request->input('child_ids', []),
            ), 202);
        } catch (AlbumNotFoundException) {
            return $this->notFoundResponse();
        } catch (AlbumTenantScopeViolationException) {
            return $this->tenantScopeViolationResponse();
        } catch (ChildTenantScopeViolationException $exception) {
            return $this->validationErrorResponse('child_ids', $exception->getMessage());
        }
    }

    public function batchStatus(Request $request, string $uploadRequestId, PhotoManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            return response()->json($service->getUploadBatchStatus($staff, $uploadRequestId));
        } catch (PhotoNotFoundException|AlbumNotFoundException) {
            return $this->notFoundResponse();
        } catch (AlbumTenantScopeViolationException) {
            return $this->tenantScopeViolationResponse();
        }
    }

    public function index(ListPhotosRequest $request, PhotoManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            $paginator = $service->listPhotos(
                $staff,
                $request->filled('album_id') ? $request->string('album_id')->toString() : null,
                $request->filled('child_id') ? $request->string('child_id')->toString() : null,
                $request->filled('keyword') ? $request->string('keyword')->toString() : null,
                $request->filled('price_status') ? $request->string('price_status')->toString() : null,
                $request->input('price_min') !== null ? $request->integer('price_min') : null,
                $request->input('price_max') !== null ? $request->integer('price_max') : null,
                $request->filled('preview_status') ? $request->string('preview_status')->toString() : null,
                $request->filled('created_from') ? $request->string('created_from')->toString() : null,
                $request->filled('created_to') ? $request->string('created_to')->toString() : null,
                $request->integer('page', 1),
                $request->integer('per_page', 20),
            );

            return response()->json([
                'data' => array_map(fn (Photo $photo): array => $service->formatPhotoSummary($photo), $paginator->items()),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (AlbumNotFoundException) {
            return $this->notFoundResponse();
        } catch (AlbumTenantScopeViolationException) {
            return $this->tenantScopeViolationResponse();
        } catch (ChildTenantScopeViolationException $exception) {
            return $this->validationErrorResponse('child_id', $exception->getMessage());
        }
    }

    public function show(Request $request, string $photoId, PhotoManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            return response()->json($service->findPhotoDetail($staff, $photoId));
        } catch (PhotoNotFoundException|AlbumNotFoundException) {
            return $this->notFoundResponse();
        } catch (AlbumTenantScopeViolationException) {
            return $this->tenantScopeViolationResponse();
        }
    }

    public function update(UpdatePhotoRequest $request, string $photoId, PhotoManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            return response()->json($service->updatePhoto(
                $staff,
                $photoId,
                $request->exists('album_id'),
                $request->exists('album_id') && $request->input('album_id') !== null ? $request->string('album_id')->toString() : null,
                $request->exists('price'),
                $request->exists('price') && $request->input('price') !== null ? $request->integer('price') : null,
                $request->exists('child_ids'),
                $request->input('child_ids', []),
            ));
        } catch (PhotoNotFoundException|AlbumNotFoundException) {
            return $this->notFoundResponse();
        } catch (AlbumTenantScopeViolationException) {
            return $this->tenantScopeViolationResponse();
        } catch (PhotoNotReadyForUpdateException) {
            return response()->json([
                'message' => 'Photo is not ready for update',
                'code' => 'PHOTO_NOT_READY_FOR_UPDATE',
            ], 409);
        } catch (ChildTenantScopeViolationException $exception) {
            return $this->validationErrorResponse('child_ids', $exception->getMessage());
        }
    }

    private function resolveAuthenticatedStaff(Request $request): ?KindergartenStaff
    {
        $staff = $request->user('staff');

        return $staff instanceof KindergartenStaff ? $staff : null;
    }

    private function unauthenticatedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Unauthenticated',
            'code' => 'STAFF_AUTH_REQUIRED',
        ], 401);
    }

    private function tenantScopeViolationResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Tenant scope violation',
            'code' => 'TENANT_SCOPE_VIOLATION',
        ], 403);
    }

    private function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Album or photo not found',
            'code' => 'ALBUM_OR_PHOTO_NOT_FOUND',
        ], 404);
    }

    private function validationErrorResponse(string $field, string $message): JsonResponse
    {
        return response()->json([
            'message' => 'Validation failed',
            'code' => 'VALIDATION_ERROR',
            'errors' => [
                $field => [$message],
            ],
        ], 422);
    }
}
