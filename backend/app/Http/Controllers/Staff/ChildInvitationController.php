<?php

namespace App\Http\Controllers\Staff;

use App\Application\Staff\Invitation\ChildInvitationService;
use App\Domain\Child\Exceptions\ChildNotFoundException;
use App\Domain\Child\Exceptions\ChildTenantScopeViolationException;
use App\Domain\Invitation\Exceptions\InvitationAlreadyRevokedException;
use App\Domain\Invitation\Exceptions\InvitationAlreadyUsedException;
use App\Domain\Invitation\Exceptions\InvitationNotFoundException;
use App\Domain\Invitation\Exceptions\InvitationReissueLimitExceededException;
use App\Domain\Invitation\Exceptions\InvitationTenantScopeViolationException;
use App\Domain\Invitation\Exceptions\InvitationTokenMismatchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CreateChildInvitationRequest;
use App\Http\Requests\Staff\ListChildInvitationsRequest;
use App\Http\Requests\Staff\PrintChildInvitationRequest;
use App\Http\Requests\Staff\ReissueChildInvitationRequest;
use App\Http\Requests\Staff\RevokeChildInvitationRequest;
use App\Models\ChildInvitation;
use App\Models\KindergartenStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ChildInvitationController extends Controller
{
    public function store(
        CreateChildInvitationRequest $request,
        string $childId,
        ChildInvitationService $service,
    ): JsonResponse {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            $result = $service->createInvitation(
                $staff,
                $childId,
                $request->string('label')->toString(),
                $request->filled('expires_in_days') ? $request->integer('expires_in_days') : null,
            );

            return response()->json($result, 201);
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

    public function index(
        ListChildInvitationsRequest $request,
        string $childId,
        ChildInvitationService $service,
    ): JsonResponse {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            $paginator = $service->listInvitations(
                $staff,
                $childId,
                $request->string('status')->toString() !== '' ? $request->string('status')->toString() : null,
                $request->integer('page', 1),
                $request->integer('per_page', 20),
            );
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

        $data = array_map(static fn (ChildInvitation $invitation): array => [
            'invitation_id' => $invitation->id,
            'label' => $invitation->label,
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'used_at' => $invitation->used_at?->toIso8601String(),
            'revoked_at' => $invitation->revoked_at?->toIso8601String(),
        ], $paginator->items());

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function print(
        PrintChildInvitationRequest $request,
        string $invitationId,
        ChildInvitationService $service,
    ): JsonResponse|Response {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            $pdf = $service->renderPrintPdf($staff, $invitationId, $request->string('token')->toString());

            return response($pdf, 200)->header('Content-Type', 'application/pdf');
        } catch (InvitationNotFoundException) {
            return response()->json([
                'message' => 'Invitation not found',
                'code' => 'INVITATION_NOT_FOUND',
            ], 404);
        } catch (InvitationTenantScopeViolationException) {
            return response()->json([
                'message' => 'Tenant scope violation',
                'code' => 'TENANT_SCOPE_VIOLATION',
            ], 403);
        } catch (InvitationTokenMismatchException) {
            return response()->json([
                'message' => 'Invitation token mismatch',
                'code' => 'INVITATION_TOKEN_MISMATCH',
            ], 403);
        }
    }

    public function revoke(
        RevokeChildInvitationRequest $request,
        string $invitationId,
        ChildInvitationService $service,
    ): JsonResponse {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            $result = $service->revokeInvitation(
                $staff,
                $invitationId,
                $request->filled('reason') ? $request->string('reason')->toString() : null,
            );

            return response()->json($result);
        } catch (InvitationNotFoundException) {
            return response()->json([
                'message' => 'Invitation not found',
                'code' => 'INVITATION_NOT_FOUND',
            ], 404);
        } catch (InvitationTenantScopeViolationException) {
            return response()->json([
                'message' => 'Tenant scope violation',
                'code' => 'TENANT_SCOPE_VIOLATION',
            ], 403);
        } catch (InvitationAlreadyUsedException) {
            return response()->json([
                'message' => 'Invitation already used',
                'code' => 'INVITATION_ALREADY_USED',
            ], 409);
        }
    }

    public function reissue(
        ReissueChildInvitationRequest $request,
        string $invitationId,
        ChildInvitationService $service,
    ): JsonResponse {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            $result = $service->reissueInvitation(
                $staff,
                $invitationId,
                $request->filled('label') ? $request->string('label')->toString() : null,
                $request->filled('expires_in_days') ? $request->integer('expires_in_days') : null,
            );

            return response()->json($result, 201);
        } catch (InvitationNotFoundException) {
            return response()->json([
                'message' => 'Invitation not found',
                'code' => 'INVITATION_NOT_FOUND',
            ], 404);
        } catch (InvitationTenantScopeViolationException) {
            return response()->json([
                'message' => 'Tenant scope violation',
                'code' => 'TENANT_SCOPE_VIOLATION',
            ], 403);
        } catch (InvitationAlreadyUsedException) {
            return response()->json([
                'message' => 'Invitation already used',
                'code' => 'INVITATION_ALREADY_USED',
            ], 409);
        } catch (InvitationAlreadyRevokedException) {
            return response()->json([
                'message' => 'Invitation already revoked',
                'code' => 'INVITATION_ALREADY_REVOKED',
            ], 409);
        } catch (InvitationReissueLimitExceededException) {
            return response()->json([
                'message' => 'Invitation reissue limit exceeded',
                'code' => 'INVITATION_REISSUE_LIMIT_EXCEEDED',
            ], 409);
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
