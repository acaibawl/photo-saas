<?php

namespace App\Http\Controllers\PublicApi;

use App\Application\Staff\Invitation\StaffInvitationService;
use App\Domain\Staff\Exceptions\StaffEmailAlreadyExistsException;
use App\Domain\Staff\Exceptions\StaffInvitationAlreadyAcceptedException;
use App\Domain\Staff\Exceptions\StaffInvitationInvalidOrExpiredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\AcceptStaffInvitationRequest;
use Illuminate\Http\JsonResponse;

class StaffInvitationPublicController extends Controller
{
    public function preview(string $rawToken, StaffInvitationService $service): JsonResponse
    {
        try {
            return response()->json($service->previewInvitation($rawToken));
        } catch (StaffInvitationInvalidOrExpiredException) {
            return response()->json([
                'message' => 'Staff invitation is invalid or expired',
                'code' => 'STAFF_INVITATION_INVALID_OR_EXPIRED',
            ], 403);
        }
    }

    public function accept(
        AcceptStaffInvitationRequest $request,
        string $rawToken,
        StaffInvitationService $service,
    ): JsonResponse {
        try {
            $result = $service->acceptInvitation(
                $rawToken,
                $request->string('password')->toString(),
                $request->ip() ?? '127.0.0.1',
                $request->userAgent(),
            );

            $refreshToken = $result['refresh_token'];
            unset($result['refresh_token']);

            return response()->json($result)
                ->withCookie(cookie('refresh_token', $refreshToken, 60 * 24 * 14, '/staff/auth/refresh', null, true, true, false, 'strict'));
        } catch (StaffInvitationAlreadyAcceptedException) {
            return response()->json([
                'message' => 'Staff invitation already accepted',
                'code' => 'STAFF_INVITATION_ALREADY_ACCEPTED',
            ], 409);
        } catch (StaffEmailAlreadyExistsException) {
            return response()->json([
                'message' => 'Staff email already exists',
                'code' => 'STAFF_EMAIL_ALREADY_EXISTS',
            ], 409);
        } catch (StaffInvitationInvalidOrExpiredException) {
            return response()->json([
                'message' => 'Staff invitation is invalid or expired',
                'code' => 'STAFF_INVITATION_INVALID_OR_EXPIRED',
            ], 403);
        }
    }
}
