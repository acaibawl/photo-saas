<?php

namespace App\Http\Controllers\Guardian;

use App\Application\Guardian\Auth\GuardianAuthService;
use App\Application\Guardian\Auth\GuardianTokenService;
use App\Domain\Guardian\Exceptions\GuardianLoginRateLimitedException;
use App\Domain\Guardian\Exceptions\GuardianRefreshTokenReuseDetectedException;
use App\Domain\Guardian\Exceptions\InvalidGuardianCredentialsException;
use App\Domain\Guardian\Exceptions\InvalidGuardianRefreshTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guardian\LoginGuardianRequest;
use App\Http\Requests\Guardian\RefreshGuardianRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
                ->withCookie(cookie('refresh_token', $refreshToken, 60 * 24 * 14, '/guardian/auth/refresh', null, true, true, false, 'none'));
        } catch (InvalidGuardianCredentialsException) {
            return response()->json([
                'message' => 'Invalid guardian credentials',
                'code' => 'GUARDIAN_AUTH_INVALID_CREDENTIALS',
            ], 401);
        } catch (GuardianLoginRateLimitedException) {
            return response()->json([
                'message' => 'Too many login attempts',
                'code' => 'GUARDIAN_AUTH_RATE_LIMITED',
            ], 429);
        }
    }

    public function refresh(RefreshGuardianRequest $request, GuardianAuthService $service): JsonResponse
    {
        $originCheck = $this->validateRefreshRequestOrigin($request);

        if ($originCheck !== null) {
            return $originCheck;
        }

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
                ->withCookie(cookie('refresh_token', $refreshToken, 60 * 24 * 14, '/guardian/auth/refresh', null, true, true, false, 'none'));
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

    public function logout(Request $request, GuardianTokenService $tokenService): JsonResponse
    {
        $guardian = $request->user('guardian');

        if ($guardian === null) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'GUARDIAN_AUTH_FORBIDDEN',
            ], 403);
        }

        $revokedCount = $tokenService->revokeAllActiveRefreshTokensForGuardian($guardian->id);

        return response()->json([
            'revoked_count' => $revokedCount,
        ]);
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

    public function verifyBySignedUrl(Request $request, GuardianAuthService $service): JsonResponse|RedirectResponse
    {
        try {
            $emailVerifiedAt = $service->verifyEmail(
                (string) $request->route('id'),
                (string) $request->route('hash'),
            );
        } catch (\InvalidArgumentException) {
            if (! $request->expectsJson()) {
                return $this->redirectToFrontendVerificationResult('invalid');
            }

            return response()->json([
                'message' => 'Invalid verification link',
            ], 403);
        }

        if (! $request->expectsJson()) {
            $resultPath = $this->frontendVerificationResultPath('success');
            $guardian = $request->user('guardian');

            if ($guardian !== null) {
                return $this->redirectToFrontendPath($resultPath);
            }

            return $this->redirectToGuardianLogin($resultPath);
        }

        return response()->json([
            'email_verified_at' => $emailVerifiedAt,
        ]);
    }

    private function frontendVerificationResultPath(string $status): string
    {
        return '/guardian/email-verification/result?status='.urlencode($status);
    }

    private function redirectToFrontendVerificationResult(string $status): RedirectResponse
    {
        return $this->redirectToFrontendPath($this->frontendVerificationResultPath($status));
    }

    private function redirectToFrontendPath(string $path): RedirectResponse
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away($base.$path);
    }

    private function redirectToGuardianLogin(string $safeReturnTo): RedirectResponse
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $loginUrl = $base.'/guardian/login?return_to='.rawurlencode($safeReturnTo);

        return redirect()->away($loginUrl);
    }

    private function validateRefreshRequestOrigin(Request $request): ?JsonResponse
    {
        $origin = $request->header('Origin');

        if (! is_string($origin) || trim($origin) === '') {
            return null;
        }

        $allowedOrigins = array_filter([
            rtrim((string) config('app.frontend_url'), '/'),
            rtrim((string) config('cors.allowed_origins.0', ''), '/'),
        ]);

        foreach ($allowedOrigins as $allowedOrigin) {
            if (strtolower($allowedOrigin) === strtolower($origin)) {
                return null;
            }
        }

        return response()->json([
            'message' => 'Forbidden',
            'code' => 'INVALID_ORIGIN',
        ], 403);
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
