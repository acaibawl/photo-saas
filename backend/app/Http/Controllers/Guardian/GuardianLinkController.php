<?php

namespace App\Http\Controllers\Guardian;

use App\Application\Guardian\Link\GuardianLinkService;
use App\Domain\Guardian\Exceptions\GuardianInvitationAlreadyUsedException;
use App\Domain\Guardian\Exceptions\GuardianInvitationInvalidOrExpiredException;
use App\Domain\GuardianLink\Exceptions\GuardianChildLinkAlreadyExistsException;
use App\Http\Controllers\Controller;
use App\Models\Guardian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardianLinkController extends Controller
{
    public function acceptInvitation(Request $request, string $rawToken, GuardianLinkService $service): JsonResponse
    {
        $guardian = $request->user('guardian');

        if (! $guardian instanceof Guardian) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'GUARDIAN_AUTH_REQUIRED',
            ], 401);
        }

        try {
            return response()->json($service->acceptInvitation($guardian, $rawToken));
        } catch (GuardianInvitationAlreadyUsedException) {
            return response()->json([
                'message' => 'Invitation already used',
                'code' => 'INVITATION_ALREADY_USED',
            ], 409);
        } catch (GuardianChildLinkAlreadyExistsException) {
            return response()->json([
                'message' => 'Guardian child link already exists',
                'code' => 'GUARDIAN_CHILD_LINK_ALREADY_EXISTS',
            ], 409);
        } catch (GuardianInvitationInvalidOrExpiredException) {
            return response()->json([
                'message' => 'Invitation is invalid or expired',
                'code' => 'INVITATION_INVALID_OR_EXPIRED',
            ], 403);
        }
    }

    public function index(Request $request, GuardianLinkService $service): JsonResponse
    {
        $guardian = $request->user('guardian');

        if (! $guardian instanceof Guardian) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'GUARDIAN_AUTH_REQUIRED',
            ], 401);
        }

        return response()->json($service->listActiveChildren($guardian));
    }
}
