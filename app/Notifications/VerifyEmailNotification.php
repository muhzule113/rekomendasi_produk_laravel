<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->from(config('mail.from.address'), 'Toko Sinar Manis')
            ->subject('Verifikasi Email Pelanggan — Toko Sinar Manis')
            ->greeting('Halo ' . ($notifiable->nama ?: 'Pelanggan') . ',')
            ->line('Terima kasih telah mendaftar sebagai Pelanggan di Toko Sinar Manis.')
            ->line('Silakan verifikasi alamat email Anda untuk membuka fitur belanja dan Rekomendasi Personal.')
            ->action('Verifikasi Email', $this->verificationUrl($notifiable))
            ->line('Tautan verifikasi ini berlaku selama ' . $this->expirationInMinutes() . ' menit.')
            ->line('Jika Anda tidak membuat pendaftaran ini, abaikan email ini.');
    }

    public function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes($this->expirationInMinutes()),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    private function expirationInMinutes(): int
    {
        return (int) config('auth.verification.expire', 60);
    }
}
