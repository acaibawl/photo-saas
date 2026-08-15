<?php

namespace App\Http\Controllers\Staff;

use App\Application\Staff\Album\AlbumManagementService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CreateAlbumRequest;
use App\Models\KindergartenStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
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
