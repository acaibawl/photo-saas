<?php

namespace App\Http\Controllers\PublicApi;

use App\Application\Guardian\Auth\GuardianAuthService;
use App\Domain\Guardian\Exceptions\GuardianInvitationAlreadyUsedException;
use App\Domain\Guardian\Exceptions\GuardianInvitationInvalidOrExpiredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\AcceptChildInvitationRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;

class ChildInvitationPublicController extends Controller
{
    public function preview(string $rawToken, GuardianAuthService $service): JsonResponse
    {
        try {
            return response()->json($service->previewInvitation($rawToken));
        } catch (GuardianInvitationInvalidOrExpiredException) {
            return response()->json([
                'message' => 'Invitation is invalid or expired',
                'code' => 'INVITATION_INVALID_OR_EXPIRED',
            ], 403);
        }
    }

    public function accept(
        AcceptChildInvitationRequest $request,
        string $rawToken,
        GuardianAuthService $service,
    ): JsonResponse {
        try {
            $result = $service->acceptInvitation(
                $rawToken,
                $request->string('name')->toString(),
                $request->string('email')->toString(),
                $request->string('password')->toString(),
                $request->ip() ?? '127.0.0.1',
                $request->userAgent(),
            );

            $refreshToken = $result['refresh_token'];
            unset($result['refresh_token']);

            return response()->json($result)
                ->withCookie(cookie('refresh_token', $refreshToken, 60 * 24 * 14, '/guardian/auth/refresh', null, true, true, false, 'strict'));
        } catch (GuardianInvitationAlreadyUsedException) {
            return response()->json([
                'message' => 'Invitation already used',
                'code' => 'INVITATION_ALREADY_USED',
            ], 409);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Guardian email already exists',
                'code' => 'GUARDIAN_EMAIL_ALREADY_EXISTS',
            ], 409);
        } catch (GuardianInvitationInvalidOrExpiredException) {
            return response()->json([
                'message' => 'Invitation is invalid or expired',
                'code' => 'INVITATION_INVALID_OR_EXPIRED',
            ], 403);
        }
    }
}
