<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $rateLimitResponse = static function (string $message): \Closure {
            return static function (Request $request, array $headers) use ($message) {
                $retryAfter = (int) ($headers['Retry-After'] ?? 0);
                $retryMessage = $retryAfter > 0
                    ? ' Coba lagi dalam ' . $retryAfter . ' detik.'
                    : '';

                return response()->view('errors.rate-limit', [
                    'message' => $message . $retryMessage,
                    'retryAfter' => $retryAfter,
                    'backRoute' => $request->routeIs('register.post')
                        ? 'register'
                        : 'verification.notice',
                ], 429, $headers);
            };
        };

        RateLimiter::for('registration', function (Request $request) use ($rateLimitResponse) {
            $ip = $request->ip() ?: 'unknown';
            $response = $rateLimitResponse('Terlalu banyak pendaftaran dari alamat jaringan ini.');

            return [
                Limit::perMinute(5, 10)
                    ->by('registration:10-minutes|' . $ip)
                    ->response($response),
                Limit::perDay(20)
                    ->by('registration:day|' . $ip)
                    ->response($response),
            ];
        });

        RateLimiter::for('verification-resend', function (Request $request) use ($rateLimitResponse) {
            if ($request->user()?->hasVerifiedEmail()) {
                return Limit::none();
            }

            $customerId = (string) ($request->user()?->getAuthIdentifier() ?? 'unknown');
            $response = $rateLimitResponse('Terlalu banyak permintaan pengiriman ulang email verifikasi.');

            return [
                Limit::perMinute(1)
                    ->by('verification-resend:minute|' . $customerId)
                    ->response($response),
                Limit::perHour(5)
                    ->by('verification-resend:hour|' . $customerId)
                    ->response($response),
            ];
        });
    }
}
