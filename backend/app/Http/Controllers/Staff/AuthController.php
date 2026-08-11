<?php

namespace App\Http\Controllers\Staff;

use App\Application\Staff\Auth\StaffAuthService;
use App\Domain\Staff\Exceptions\InvalidStaffCredentialsException;
use App\Domain\Staff\Exceptions\InvalidStaffRefreshTokenException;
use App\Domain\Staff\Exceptions\StaffRefreshTokenReuseDetectedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\LoginStaffRequest;
use App\Http\Requests\Staff\LogoutStaffRequest;
use App\Http\Requests\Staff\RefreshStaffRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginStaffRequest $request, StaffAuthService $service): JsonResponse
    {
        try {
            $result = $service->login(
                $request->string('email')->toString(),
                $request->string('password')->toString(),
                $request->ip() ?? '127.0.0.1',
                $request->userAgent(),
            );

            $refreshToken = $result['refresh_token'];
            unset($result['refresh_token']);

            return response()->json($result)
                ->withCookie(cookie('refresh_token', $refreshToken, 60 * 24 * 14, '/staff/auth/refresh', null, true, true, false, 'strict'));
        } catch (InvalidStaffCredentialsException) {
            return response()->json([
                'message' => 'Invalid staff credentials',
                'code' => 'STAFF_AUTH_INVALID_CREDENTIALS',
            ], 401);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => 'Too many login attempts',
                'code' => 'STAFF_AUTH_RATE_LIMITED',
            ], 429);
        }
    }

    public function refresh(RefreshStaffRequest $request, StaffAuthService $service): JsonResponse
    {
        $refreshToken = $this->resolveRefreshToken($request);

        if ($refreshToken === null || trim($refreshToken) === '') {
            return response()->json([
                'message' => 'Validation failed',
                'code' => 'VALIDATION_ERROR',
                'errors' => [
                    'refresh_token' => ['validation.required'],
                ],
            ], 422);
        }

        try {
            $result = $service->refresh(
                $refreshToken,
                $request->ip() ?? '127.0.0.1',
                $request->userAgent(),
            );

            $refreshToken = $result['refresh_token'];
            unset($result['refresh_token']);

            return response()->json($result)
                ->withCookie(cookie('refresh_token', $refreshToken, 60 * 24 * 14, '/staff/auth/refresh', null, true, true, false, 'strict'));
        } catch (InvalidStaffRefreshTokenException) {
            return response()->json([
                'message' => 'Invalid refresh token',
                'code' => 'STAFF_AUTH_REFRESH_INVALID',
            ], 401);
        } catch (StaffRefreshTokenReuseDetectedException) {
            return response()->json([
                'message' => 'Refresh token reuse detected',
                'code' => 'STAFF_AUTH_REFRESH_REUSE_DETECTED',
            ], 401);
        }
    }

    public function logout(LogoutStaffRequest $request, StaffAuthService $service): JsonResponse
    {
        $staff = $request->user('staff');

        if ($staff === null) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'STAFF_AUTH_FORBIDDEN',
            ], 403);
        }

        $allSessions = $request->boolean('all_sessions', false);
        $refreshToken = $this->resolveRefreshToken($request);

        if (! $allSessions && ($refreshToken === null || trim($refreshToken) === '')) {
            return response()->json([
                'message' => 'Validation failed',
                'code' => 'VALIDATION_ERROR',
                'errors' => [
                    'refresh_token' => ['validation.required'],
                ],
            ], 422);
        }

        $revokedCount = $service->logout(
            $staff,
            $allSessions,
            $refreshToken,
        );

        return response()->json([
            'revoked_count' => $revokedCount,
        ]);
    }

    private function resolveRefreshToken(Request $request): ?string
    {
        $refreshToken = $request->input('refresh_token');

        if (is_string($refreshToken) && trim($refreshToken) !== '') {
            return $refreshToken;
        }

        $cookieValue = $request->cookie('refresh_token');
        if (is_string($cookieValue) && trim($cookieValue) !== '') {
            return $cookieValue;
        }

        $cookieHeader = $request->header('Cookie');
        if (is_string($cookieHeader) && $cookieHeader !== '') {
            foreach (explode(';', $cookieHeader) as $cookie) {
                [$name, $value] = array_pad(explode('=', trim($cookie), 2), 2, '');

                if ($name === 'refresh_token' && trim($value) !== '') {
                    return urldecode($value);
                }
            }
        }

        return null;
    }

    public function me(Request $request): JsonResponse
    {
        $staff = $request->user('staff');

        if ($staff === null) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'STAFF_AUTH_FORBIDDEN',
            ], 403);
        }

        return response()->json([
            'id' => $staff->id,
            'kindergarten_id' => $staff->kindergarten_id,
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => $staff->role instanceof \BackedEnum ? $staff->role->value : $staff->role,
        ]);
    }
}
