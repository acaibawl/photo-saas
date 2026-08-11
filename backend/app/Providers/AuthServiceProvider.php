<?php

namespace App\Providers;

use Illuminate\Auth\RequestGuard;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [];

    public function boot(): void
    {
        Auth::extend('jwt', function ($app, $name, array $config) {
            $provider = Auth::createUserProvider($config['provider']);

            return new RequestGuard(function ($request) {
                $token = $request->bearerToken();

                if ($token === null) {
                    return null;
                }

                $user = Auth::guard('api')->setToken($token)->user();

                return $user;
            }, $request = app('request'), $provider);
        });
    }
}
