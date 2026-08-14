<?php

namespace App\Http\Controllers\Staff;

use App\Application\Staff\GuardianLink\GuardianLinkManagementService;
use App\Domain\Child\Exceptions\ChildNotFoundException;
use App\Domain\Child\Exceptions\ChildTenantScopeViolationException;
use App\Domain\GuardianLink\Exceptions\GuardianLinkAlreadyUnlinkedException;
use App\Domain\GuardianLink\Exceptions\GuardianLinkNotFoundException;
use App\Domain\GuardianLink\Exceptions\GuardianLinkNotUnlinkedException;
use App\Domain\GuardianLink\Exceptions\GuardianLinkTenantScopeViolationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ListGuardianLinksRequest;
use App\Http\Requests\Staff\UnlinkGuardianLinkRequest;
use App\Models\GuardianChild;
use App\Models\KindergartenStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardianLinkController extends Controller
{
    public function index(
        ListGuardianLinksRequest $request,
        string $childId,
        GuardianLinkManagementService $service,
    ): JsonResponse {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            $paginator = $service->listGuardianLinks(
                $staff,
                $childId,
                $request->getIncludeUnlinked(),
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

        $data = array_map(static fn (GuardianChild $link): array => [
            'link_id' => $link->id,
            'guardian_id' => $link->guardian_id,
            'guardian_name' => $link->guardian?->name,
            'guardian_email' => $link->guardian?->email,
            'label' => $link->label,
            'linked_at' => $link->linked_at?->toIso8601String(),
            'unlinked_at' => $link->unlinked_at?->toIso8601String(),
        ], $paginator->items());

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function unlink(
        UnlinkGuardianLinkRequest $request,
        string $linkId,
        GuardianLinkManagementService $service,
    ): JsonResponse {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            $result = $service->unlinkGuardianLink(
                $staff,
                $linkId,
                $request->filled('reason') ? $request->string('reason')->toString() : null,
                $request->string('confirm_text')->toString(),
            );

            return response()->json($result);
        } catch (GuardianLinkNotFoundException) {
            return response()->json([
                'message' => 'Guardian link not found',
                'code' => 'GUARDIAN_LINK_NOT_FOUND',
            ], 404);
        } catch (GuardianLinkTenantScopeViolationException) {
            return response()->json([
                'message' => 'Tenant scope violation',
                'code' => 'TENANT_SCOPE_VIOLATION',
            ], 403);
        } catch (GuardianLinkAlreadyUnlinkedException) {
            return response()->json([
                'message' => 'Guardian link already unlinked',
                'code' => 'GUARDIAN_LINK_ALREADY_UNLINKED',
            ], 409);
        }
    }

    public function restore(
        Request $request,
        string $linkId,
        GuardianLinkManagementService $service,
    ): JsonResponse {
        $staff = $this->resolveAuthenticatedStaff($request);

        if ($staff === null) {
            return $this->unauthenticatedResponse();
        }

        try {
            $result = $service->restoreGuardianLink($staff, $linkId);

            return response()->json($result);
        } catch (GuardianLinkNotFoundException) {
            return response()->json([
                'message' => 'Guardian link not found',
                'code' => 'GUARDIAN_LINK_NOT_FOUND',
            ], 404);
        } catch (GuardianLinkTenantScopeViolationException) {
            return response()->json([
                'message' => 'Tenant scope violation',
                'code' => 'TENANT_SCOPE_VIOLATION',
            ], 403);
        } catch (GuardianLinkNotUnlinkedException) {
            return response()->json([
                'message' => 'Guardian link is not unlinked',
                'code' => 'GUARDIAN_LINK_NOT_UNLINKED',
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
