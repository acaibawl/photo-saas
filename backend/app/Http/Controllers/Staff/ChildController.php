<?php

namespace App\Http\Controllers\Staff;

use App\Application\Staff\Child\ChildManagementService;
use App\Domain\Child\ChildStatus;
use App\Domain\Child\Exceptions\ChildNotFoundException;
use App\Domain\Child\Exceptions\ChildStatusTransitionNotAllowedException;
use App\Domain\Child\Exceptions\ChildTenantScopeViolationException;
use App\Domain\ChildClass\Exceptions\ChildClassNotFoundException;
use App\Domain\ChildClass\Exceptions\ChildClassTenantScopeViolationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CreateChildRequest;
use App\Http\Requests\Staff\ListChildrenRequest;
use App\Http\Requests\Staff\UpdateChildRequest;
use App\Http\Requests\Staff\UpdateChildStatusRequest;
use App\Models\Child;
use App\Models\KindergartenStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChildController extends Controller
{
    public function store(CreateChildRequest $request, ChildManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            $child = $service->createChild(
                $staff,
                $request->string('name')->toString(),
                $request->string('child_class_id')->toString(),
                $request->filled('status')
                    ? ChildStatus::from($request->string('status')->toString())
                    : ChildStatus::Enrolled,
            );

            return response()->json($child, 201);
        } catch (ChildClassNotFoundException) {
            return response()->json(['message' => 'Child class not found', 'code' => 'CHILD_CLASS_NOT_FOUND'], 404);
        } catch (ChildClassTenantScopeViolationException) {
            return response()->json(['message' => 'Tenant scope violation', 'code' => 'TENANT_SCOPE_VIOLATION'], 403);
        }
    }

    public function index(ListChildrenRequest $request, ChildManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            $paginator = $service->listChildren(
                $staff,
                $request->string('status')->toString() !== '' ? $request->string('status')->toString() : null,
                $request->string('child_class_id')->toString() !== '' ? $request->string('child_class_id')->toString() : null,
                $request->string('keyword')->toString() !== '' ? $request->string('keyword')->toString() : null,
                $request->integer('page', 1),
                $request->integer('per_page', 20),
            );
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

        $data = array_map(fn (Child $child): array => $this->formatChild($child, true), $paginator->items());

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, string $childId, ChildManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            $child = $service->findChild($staff, $childId);

            return response()->json($this->formatChild($child, true));
        } catch (ChildNotFoundException) {
            return response()->json([
                'message' => 'Child not found',
                'code' => 'CHILD_NOT_FOUND',
            ], 404);
        } catch (ChildTenantScopeViolationException) {
            return response()->json([
                'message' => 'Tenant scope violation',
                'code' => 'TENANT_SCOPE_VIOLATION',
            ], 403);
        }
    }

    public function update(UpdateChildRequest $request, string $childId, ChildManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            return response()->json($service->updateChild(
                $staff,
                $childId,
                $request->filled('name') ? $request->string('name')->toString() : null,
                $request->filled('child_class_id') ? $request->string('child_class_id')->toString() : null,
            ));
        } catch (ChildNotFoundException) {
            return response()->json([
                'message' => 'Child not found',
                'code' => 'CHILD_NOT_FOUND',
            ], 404);
        } catch (ChildTenantScopeViolationException) {
            return response()->json([
                'message' => 'Tenant scope violation',
                'code' => 'TENANT_SCOPE_VIOLATION',
            ], 403);
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

    public function status(UpdateChildStatusRequest $request, string $childId, ChildManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            return response()->json($service->updateChildStatus(
                $staff,
                $childId,
                ChildStatus::from($request->string('status')->toString()),
            ));
        } catch (ChildNotFoundException) {
            return response()->json([
                'message' => 'Child not found',
                'code' => 'CHILD_NOT_FOUND',
            ], 404);
        } catch (ChildTenantScopeViolationException) {
            return response()->json([
                'message' => 'Tenant scope violation',
                'code' => 'TENANT_SCOPE_VIOLATION',
            ], 403);
        } catch (ChildStatusTransitionNotAllowedException) {
            return response()->json([
                'message' => 'Child status transition not allowed',
                'code' => 'CHILD_STATUS_TRANSITION_NOT_ALLOWED',
            ], 409);
        }
    }

    private function resolveAuthenticatedStaff(Request $request): ?KindergartenStaff
    {
        $staff = $request->user('staff');

        return $staff instanceof KindergartenStaff ? $staff : null;
    }

    private function formatChild(Child $child, bool $includeUpdatedAt): array
    {
        $class = $child->childClass;

        return array_filter([
            'id' => $child->id,
            'kindergarten_id' => $class->kindergarten_id,
            'class_id' => $child->child_class_id,
            'name' => $child->name,
            'class_name' => $class->name,
            'status' => $child->status->value,
            'created_at' => $child->created_at?->toIso8601String(),
            'updated_at' => $includeUpdatedAt ? $child->updated_at?->toIso8601String() : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function unauthenticatedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Unauthenticated',
            'code' => 'STAFF_AUTH_REQUIRED',
        ], 401);
    }
}
