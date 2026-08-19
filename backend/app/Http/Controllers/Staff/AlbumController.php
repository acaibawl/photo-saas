<?php

namespace App\Http\Controllers\Staff;

use App\Application\Staff\Album\AlbumManagementService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CreateAlbumRequest;
use App\Http\Requests\Staff\ListAlbumsRequest;
use App\Models\Album;
use App\Models\KindergartenStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
    public function index(ListAlbumsRequest $request, AlbumManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        $paginator = $service->listAlbums(
            $staff,
            $request->integer('page', 1),
            $request->integer('per_page', 20),
        );

        return response()->json([
            'data' => array_map(fn (Album $album): array => [
                'id' => $album->id,
                'kindergarten_id' => $album->kindergarten_id,
                'title' => $album->title,
                'event_date' => $album->event_date?->toDateString(),
                'created_at' => $album->created_at?->toIso8601String(),
                'updated_at' => $album->updated_at?->toIso8601String(),
            ], $paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(CreateAlbumRequest $request, AlbumManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        return response()->json($service->createAlbum(
            $staff,
            $request->string('title')->toString(),
            $request->string('event_date')->toString(),
        ), 201);
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
}
