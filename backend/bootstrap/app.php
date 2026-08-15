<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('*'),
        );

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            $guards = $exception->guards();
            $isGuardianRoute = in_array('guardian', $guards, true);

            return response()->json([
                'message' => $isGuardianRoute ? 'Guardian authentication is required' : 'Staff authentication is required',
                'code' => $isGuardianRoute ? 'GUARDIAN_AUTH_REQUIRED' : 'STAFF_AUTH_REQUIRED',
            ], 401);
        });

        $exceptions->render(function (InvalidSignatureException $exception, Request $request) {
            if ($request->is('guardian/auth/email/verify/*')) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Invalid verification link',
                    ], 403);
                }

                $expires = $request->query('expires');
                $isExpired = is_string($expires) && ctype_digit($expires) && now()->timestamp > (int) $expires;
                $status = $isExpired ? 'expired' : 'invalid';

                $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

                return redirect()->away($frontendUrl.'/guardian/email-verification/result?status='.$status);
            }

            return null;
        });
    })->create();
