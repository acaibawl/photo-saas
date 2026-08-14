<?php

namespace App\Http\Controllers\Guardian;

use App\Application\Guardian\Auth\GuardianAuthService;
use App\Domain\Guardian\Exceptions\GuardianRefreshTokenReuseDetectedException;
use App\Domain\Guardian\Exceptions\InvalidGuardianCredentialsException;
use App\Domain\Guardian\Exceptions\InvalidGuardianRefreshTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guardian\LoginGuardianRequest;
use App\Http\Requests\Guardian\RefreshGuardianRequest;
use App\Http\Requests\Guardian\VerifyGuardianEmailRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginGuardianRequest $request, GuardianAuthService $service): JsonResponse
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
                ->withCookie(cookie('refresh_token', $refreshToken, 60 * 24 * 14, '/guardian/auth/refresh', null, true, true, false, 'strict'));
        } catch (InvalidGuardianCredentialsException) {
            return response()->json([
                'message' => 'Invalid guardian credentials',
                'code' => 'GUARDIAN_AUTH_INVALID_CREDENTIALS',
            ], 401);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => 'Too many login attempts',
                'code' => 'GUARDIAN_AUTH_RATE_LIMITED',
            ], 429);
        }
    }

    public function refresh(RefreshGuardianRequest $request, GuardianAuthService $service): JsonResponse
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
                ->withCookie(cookie('refresh_token', $refreshToken, 60 * 24 * 14, '/guardian/auth/refresh', null, true, true, false, 'strict'));
        } catch (InvalidGuardianRefreshTokenException) {
            return response()->json([
                'message' => 'Invalid refresh token',
                'code' => 'GUARDIAN_AUTH_REFRESH_INVALID',
            ], 401);
        } catch (GuardianRefreshTokenReuseDetectedException) {
            return response()->json([
                'message' => 'Refresh token reuse detected',
                'code' => 'GUARDIAN_AUTH_REFRESH_REUSE_DETECTED',
            ], 401);
        }
    }

    public function verificationNotification(Request $request, GuardianAuthService $service): JsonResponse
    {
        $guardian = $request->user('guardian');

        if ($guardian === null) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'GUARDIAN_AUTH_FORBIDDEN',
            ], 403);
        }

        $service->sendEmailVerificationNotification($guardian);

        return response()->json(['queued' => true], 202);
    }

    public function verifyEmail(VerifyGuardianEmailRequest $request, GuardianAuthService $service): JsonResponse
    {
        $emailVerifiedAt = $service->verifyEmail(
            $request->string('id')->toString(),
            $request->string('hash')->toString(),
            $request->string('signature')->toString(),
            (int) $request->integer('expires'),
        );

        return response()->json([
            'email_verified_at' => $emailVerifiedAt,
        ]);
    }

    public function verifyBySignedUrl(Request $request, GuardianAuthService $service): JsonResponse
    {
        $emailVerifiedAt = $service->verifyEmail(
            (string) $request->route('id'),
            (string) $request->route('hash'),
            (string) $request->query('signature', ''),
            (int) $request->query('expires', 0),
        );

        return response()->json([
            'email_verified_at' => $emailVerifiedAt,
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
}
