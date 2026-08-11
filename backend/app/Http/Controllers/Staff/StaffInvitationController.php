<?php

namespace App\Http\Controllers\Staff;

use App\Application\Staff\Invitation\StaffInvitationService;
use App\Domain\Staff\Exceptions\StaffEmailAlreadyExistsException;
use App\Domain\Staff\Exceptions\StaffInvitationAlreadyAcceptedException;
use App\Domain\Staff\Exceptions\StaffInvitationAlreadyExistsException;
use App\Domain\Staff\StaffRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CreateStaffInvitationRequest;
use App\Http\Requests\Staff\ListStaffInvitationsRequest;
use App\Models\KindergartenStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffInvitationController extends Controller
{
    public function store(CreateStaffInvitationRequest $request, StaffInvitationService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedOwner($request);

        if ($staff === null) {
            return $this->forbiddenRoleResponse();
        }

        try {
            $result = $service->createInvitation(
                $staff,
                $request->string('name')->toString(),
                $request->string('email')->toString(),
                StaffRole::from($request->string('role')->toString()),
                $request->filled('expires_in_days') ? $request->integer('expires_in_days') : null,
            );

            return response()->json($result, 201);
        } catch (StaffInvitationAlreadyExistsException) {
            return response()->json([
                'message' => 'Staff invitation already exists',
                'code' => 'STAFF_INVITATION_ALREADY_EXISTS',
            ], 409);
        } catch (StaffEmailAlreadyExistsException) {
            return response()->json([
                'message' => 'Staff email already exists',
                'code' => 'STAFF_EMAIL_ALREADY_EXISTS',
            ], 409);
        }
    }

    public function index(ListStaffInvitationsRequest $request, StaffInvitationService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedOwner($request);

        if ($staff === null) {
            return $this->forbiddenRoleResponse();
        }

        $paginator = $service->listInvitations(
            $staff,
            $request->string('status')->toString() !== '' ? $request->string('status')->toString() : null,
            $request->integer('per_page', 20),
        );

        $data = array_map(function ($invitation): array {
            return [
                'invitation_id' => $invitation->id,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'status' => $this->resolveInvitationStatus($invitation),
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'accepted_at' => $invitation->accepted_at?->toIso8601String(),
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

    public function revoke(Request $request, string $invitationId, StaffInvitationService $service): JsonResponse
    {
        $staff = $this->resolveAuthenticatedOwner($request);

        if ($staff === null) {
            return $this->forbiddenRoleResponse();
        }

        try {
            $result = $service->revokeInvitation($staff, $invitationId);

            return response()->json($result);
        } catch (StaffInvitationAlreadyAcceptedException) {
            return response()->json([
                'message' => 'Staff invitation already accepted',
                'code' => 'STAFF_INVITATION_ALREADY_ACCEPTED',
            ], 409);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => 'Staff invitation not found',
                'code' => 'STAFF_INVITATION_NOT_FOUND',
            ], 404);
        }
    }

    private function resolveInvitationStatus(object $invitation): string
    {
        if ($invitation->accepted_at !== null) {
            return 'accepted';
        }

        if ($invitation->revoked_at !== null) {
            return 'revoked';
        }

        if ($invitation->expires_at->isPast()) {
            return 'expired';
        }

        return 'pending';
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
