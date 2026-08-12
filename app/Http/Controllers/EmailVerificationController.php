<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice(Request $request)
    {
        return view('auth.verify-email', [
            'verificationMinutes' => (int) config('auth.verification.expire', 60),
            'email' => $request->user()->email,
        ]);
    }

    public function verify(Request $request, string $id, string $hash)
    {
        $authenticated = $request->user();
        $customer = User::find($id);

        if (! $customer || ! hash_equals(sha1($customer->getEmailForVerification()), $hash)) {
            return $this->invalidLink('Tautan verifikasi tidak cocok dengan akun Pelanggan ini.');
        }

        if ((string) $authenticated->getAuthIdentifier() !== (string) $customer->getAuthIdentifier()) {
            return $this->invalidLink('Tautan verifikasi ini hanya dapat digunakan oleh akun yang sesuai.');
        }

        if (! $authenticated->hasVerifiedEmail()) {
            if ($authenticated->markEmailAsVerified()) {
                event(new Verified($authenticated));
            }
        }

        return redirect()->route('produk')->with(
            'success',
            'Email berhasil diverifikasi. Selamat berbelanja di Toko Sinar Manis!'
        );
    }

    public function send(Request $request)
    {
        $customer = $request->user();

        if ($customer->hasVerifiedEmail()) {
            return redirect()->route('produk')->with(
                'status',
                'Email Anda sudah terverifikasi. Anda dapat melanjutkan belanja.'
            );
        }

        $customer->sendEmailVerificationNotification();

        return redirect()->route('verification.notice')->with(
            'status',
            'Tautan verifikasi baru telah dikirim ke email Anda.'
        );
    }

    private function invalidLink(string $message)
    {
        return response()->view('auth.verification-invalid', [
            'message' => $message,
            'verificationMinutes' => (int) config('auth.verification.expire', 60),
        ], 403);
    }
}
