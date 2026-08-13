<?php

namespace App\Http\Controllers\Staff;

use App\Application\Staff\Member\StaffMemberManagementService;
use App\Domain\Staff\Exceptions\OwnerMinimumRequiredException;
use App\Domain\Staff\Exceptions\StaffDeactivateSelfForbiddenException;
use App\Domain\Staff\Exceptions\StaffMemberNotFoundException;
use App\Domain\Staff\Exceptions\StaffRoleChangeSelfForbiddenException;
use App\Domain\Staff\StaffRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ChangeStaffRoleRequest;
use App\Http\Requests\Staff\ListStaffMembersRequest;
use App\Models\KindergartenStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffMemberController extends Controller
{
    public function index(ListStaffMembersRequest $request, StaffMemberManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedOwner($request);

        if ($staff === null) {
            return $this->forbiddenRoleResponse();
        }

        $paginator = $service->listMembers(
            $staff,
            $request->string('status')->toString() !== '' ? $request->string('status')->toString() : null,
            $request->string('role')->toString() !== '' ? $request->string('role')->toString() : null,
            $request->string('keyword')->toString() !== '' ? $request->string('keyword')->toString() : null,
            $request->integer('per_page', 20),
        );

        $data = array_map(function (KindergartenStaff $member): array {
            return [
                'staff_id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role->value,
                'status' => $member->deactivated_at === null ? 'active' : 'inactive',
                'last_login_at' => $member->last_login_at?->toIso8601String(),
                'invited_at' => $member->invited_at?->toIso8601String(),
                'joined_at' => $member->joined_at?->toIso8601String(),
            ];
        }, $paginator->items());

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, string $staffId, StaffMemberManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedOwner($request);

        if ($staff === null) {
            return $this->forbiddenRoleResponse();
        }

        try {
            $member = $service->findMember($staff, $staffId);

            return response()->json([
                'staff_id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role->value,
                'status' => $member->deactivated_at === null ? 'active' : 'inactive',
                'last_login_at' => $member->last_login_at?->toIso8601String(),
                'invited_at' => $member->invited_at?->toIso8601String(),
                'joined_at' => $member->joined_at?->toIso8601String(),
                'deactivated_at' => $member->deactivated_at?->toIso8601String(),
            ]);
        } catch (StaffMemberNotFoundException) {
            return response()->json([
                'message' => 'Staff member not found',
                'code' => 'STAFF_MEMBER_NOT_FOUND',
            ], 404);
        }
    }

    public function role(ChangeStaffRoleRequest $request, string $staffId, StaffMemberManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedOwner($request);

        if ($staff === null) {
            return $this->forbiddenRoleResponse();
        }

        try {
            $result = $service->changeRole($staff, $staffId, StaffRole::from($request->string('role')->toString()));

            return response()->json($result);
        } catch (StaffMemberNotFoundException) {
            return response()->json([
                'message' => 'Staff member not found',
                'code' => 'STAFF_MEMBER_NOT_FOUND',
            ], 404);
        } catch (StaffRoleChangeSelfForbiddenException) {
            return response()->json([
                'message' => 'Changing own role is forbidden',
                'code' => 'STAFF_ROLE_CHANGE_SELF_FORBIDDEN',
            ], 409);
        } catch (OwnerMinimumRequiredException) {
            return response()->json([
                'message' => 'At least one active owner is required',
                'code' => 'OWNER_MINIMUM_REQUIRED',
            ], 409);
        }
    }

    public function deactivate(Request $request, string $staffId, StaffMemberManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedOwner($request);

        if ($staff === null) {
            return $this->forbiddenRoleResponse();
        }

        try {
            $result = $service->deactivate($staff, $staffId);

            return response()->json($result);
        } catch (StaffMemberNotFoundException) {
            return response()->json([
                'message' => 'Staff member not found',
                'code' => 'STAFF_MEMBER_NOT_FOUND',
            ], 404);
        } catch (StaffDeactivateSelfForbiddenException) {
            return response()->json([
                'message' => 'Deactivating own account is forbidden',
                'code' => 'STAFF_DEACTIVATE_SELF_FORBIDDEN',
            ], 409);
        } catch (OwnerMinimumRequiredException) {
            return response()->json([
                'message' => 'At least one active owner is required',
                'code' => 'OWNER_MINIMUM_REQUIRED',
            ], 409);
        }
    }

    public function reactivate(Request $request, string $staffId, StaffMemberManagementService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedOwner($request);

        if ($staff === null) {
            return $this->forbiddenRoleResponse();
        }

        try {
            $result = $service->reactivate($staff, $staffId);

            return response()->json($result);
        } catch (StaffMemberNotFoundException) {
            return response()->json([
                'message' => 'Staff member not found',
                'code' => 'STAFF_MEMBER_NOT_FOUND',
            ], 404);
        }
    }

    private function resolveAuthenticatedOwner(Request $request): ?KindergartenStaff
    {
        $staff = $request->user('staff');

        if (! $staff instanceof KindergartenStaff) {
            return null;
        }

        if ($staff->role !== StaffRole::Owner) {
            return null;
        }

        return $staff;
    }

    private function forbiddenRoleResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Staff role forbidden',
            'code' => 'STAFF_ROLE_FORBIDDEN',
        ], 403);
    }
}
