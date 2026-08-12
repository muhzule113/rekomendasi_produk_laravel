<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/cart',
            'api/cart/*',
            'verify-payment',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidSignatureException $exception, Request $request) {
            if (! $request->routeIs('verification.verify')) {
                return null;
            }

            return response()->view('auth.verification-invalid', [
                'message' => 'Tautan verifikasi sudah kedaluwarsa atau tidak valid. Silakan minta tautan baru.',
                'verificationMinutes' => (int) config('auth.verification.expire', 60),
            ], 403);
        });
    })->create();
