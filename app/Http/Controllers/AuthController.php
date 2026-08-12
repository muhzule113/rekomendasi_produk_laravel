<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CartService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'role' => 'required|in:pelanggan,admin',
        ]);

        $email = strtolower(trim($credentials['email']));
        $loginKey = $this->loginFailureKey($email, $request);

        if (RateLimiter::tooManyAttempts($loginKey, 5)) {
            $retryAfter = RateLimiter::availableIn($loginKey);

            return response()->view('errors.rate-limit', [
                'message' => 'Terlalu banyak percobaan login gagal. Silakan coba lagi nanti.',
                'retryAfter' => $retryAfter,
                'backRoute' => 'login',
            ], 429, ['Retry-After' => $retryAfter]);
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])
            ->where('role', $credentials['role'])
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($loginKey, 5 * 60);

            return back()->withErrors(['email' => 'Email atau password salah, atau peran tidak sesuai.'])->withInput();
        }

        if ($user->status === 'nonaktif') {
            RateLimiter::hit($loginKey, 5 * 60);

            return back()->withErrors(['email' => 'Akun Anda telah dinonaktifkan.'])->withInput();
        }

        RateLimiter::clear($loginKey);
        Auth::login($user);
        $request->session()->regenerate();

        if ($user->role === 'pelanggan') {
            app(CartService::class)->restoreOnLogin((int) $user->id_user);
        }

        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        $defaultRoute = $user->hasVerifiedEmail() ? 'produk' : 'verification.notice';
        $intended = $this->safeIntendedUrl($request);

        if ($intended !== null) {
            return redirect()->to($intended);
        }

        return redirect()->route($defaultRoute)->with(
            'status',
            $user->hasVerifiedEmail()
                ? null
                : 'Email Anda belum terverifikasi. Silakan buka tautan verifikasi yang dikirim ke email Anda.'
        );
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'no_hp' => 'required|string|max:20',
            'alamat' => 'required|string',
            'password' => 'required|min:6',
            'konfirmasi' => 'required|same:password',
        ]);

        $user = User::create([
            'nama' => $validated['nama'],
            'email' => strtolower(trim($validated['email'])),
            'no_hp' => $validated['no_hp'],
            'alamat' => $validated['alamat'],
            'password' => $validated['password'],
            'role' => 'pelanggan',
            'status' => 'aktif',
            'email_verified_at' => null,
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        app(CartService::class)->restoreOnLogin((int) $user->id_user);

        event(new Registered($user));

        return redirect()->route('verification.notice')->with(
            'status',
            'Pendaftaran berhasil. Tautan verifikasi telah dikirim ke email Anda dan berlaku selama '
                . (int) config('auth.verification.expire', 60) . ' menit.'
        );
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }

    private function loginFailureKey(string $email, Request $request): string
    {
        return 'auth:login-failure|' . $email . '|' . ($request->ip() ?: 'unknown');
    }

    private function safeIntendedUrl(Request $request): ?string
    {
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return null;
        }

        $parsed = parse_url($intended);
        if ($parsed === false) {
            return null;
        }

        if (isset($parsed['host'])) {
            $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $requestHost = $request->getHost();

            if (! in_array($parsed['host'], array_filter([$configuredHost, $requestHost]), true)) {
                return null;
            }

            $path = $parsed['path'] ?? '/';
            $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

            return str_starts_with($path, '/') ? $path . $query : null;
        }

        return str_starts_with($intended, '/') && ! str_starts_with($intended, '//')
            ? $intended
            : null;
    }
}
