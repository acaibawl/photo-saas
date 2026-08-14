<?php

namespace App\Http\Controllers\Staff;

use App\Application\Staff\ChildClass\ChildClassManagementService;
use App\Domain\ChildClass\Exceptions\ChildClassAlreadyExistsException;
use App\Domain\ChildClass\Exceptions\ChildClassInUseException;
use App\Domain\ChildClass\Exceptions\ChildClassNotFoundException;
use App\Domain\ChildClass\Exceptions\ChildClassTenantScopeViolationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CreateChildClassRequest;
use App\Http\Requests\Staff\ListChildClassesRequest;
use App\Http\Requests\Staff\UpdateChildClassRequest;
use App\Models\ChildClass;
use App\Models\KindergartenStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChildClassController extends Controller
{
    public function store(CreateChildClassRequest $request, ChildClassManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            return response()->json($service->createChildClass($staff, $request->string('name')->toString()), 201);
        } catch (ChildClassAlreadyExistsException) {
            return response()->json([
                'message' => 'Child class already exists',
                'code' => 'CHILD_CLASS_NAME_ALREADY_EXISTS',
            ], 409);
        }
    }

    public function index(ListChildClassesRequest $request, ChildClassManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        $paginator = $service->listChildClasses(
            $staff,
            $request->string('keyword')->toString() !== '' ? $request->string('keyword')->toString() : null,
            $request->integer('page', 1),
            $request->integer('per_page', 20),
        );

        return response()->json([
            'data' => array_map(fn (ChildClass $childClass): array => [
                'id' => $childClass->id,
                'kindergarten_id' => $childClass->kindergarten_id,
                'name' => $childClass->name,
                'created_at' => $childClass->created_at?->toIso8601String(),
                'updated_at' => $childClass->updated_at?->toIso8601String(),
            ], $paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, string $childClassId, ChildClassManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            return response()->json($service->findChildClass($staff, $childClassId));
        } catch (ChildClassNotFoundException) {
            return response()->json([
                'message' => 'Child class not found',
                'code' => 'CHILD_CLASS_NOT_FOUND',
            ], 404);
        } catch (ChildClassTenantScopeViolationException) {
            return response()->json([
                'message' => 'Tenant scope violation',
                'code' => 'TENANT_SCOPE_VIOLATION',
            ], 403);
        }
    }

    public function update(UpdateChildClassRequest $request, string $childClassId, ChildClassManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            return response()->json($service->updateChildClass(
                $staff,
                $childClassId,
                $request->string('name')->toString(),
            ));
        } catch (ChildClassNotFoundException) {
            return response()->json([
                'message' => 'Child class not found',
                'code' => 'CHILD_CLASS_NOT_FOUND',
            ], 404);
        } catch (ChildClassAlreadyExistsException) {
            return response()->json([
                'message' => 'Child class already exists',
                'code' => 'CHILD_CLASS_NAME_ALREADY_EXISTS',
            ], 409);
        } catch (ChildClassTenantScopeViolationException) {
            return response()->json([
                'message' => 'Tenant scope violation',
                'code' => 'TENANT_SCOPE_VIOLATION',
            ], 403);
        }
    }

    public function destroy(Request $request, string $childClassId, ChildClassManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            return response()->json($service->deleteChildClass($staff, $childClassId));
        } catch (ChildClassNotFoundException) {
            return response()->json([
                'message' => 'Child class not found',
                'code' => 'CHILD_CLASS_NOT_FOUND',
            ], 404);
        } catch (ChildClassInUseException) {
            return response()->json([
                'message' => 'Child class in use',
                'code' => 'CHILD_CLASS_IN_USE',
            ], 409);
        } catch (ChildClassTenantScopeViolationException) {
            return response()->json([
                'message' => 'Tenant scope violation',
                'code' => 'TENANT_SCOPE_VIOLATION',
            ], 403);
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
}
