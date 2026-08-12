<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory, Notifiable, MustVerifyEmailTrait;

    protected $table = 'users';
    protected $primaryKey = 'id_user';
    public $timestamps = false;

    protected $fillable = [
        'nama', 'email', 'no_hp', 'alamat', 'password', 'role', 'status', 'email_verified_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * Admin authentication is intentionally outside the customer email flow.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->role === 'admin' || ! is_null($this->email_verified_at);
    }

    public function sendEmailVerificationNotification(): void
    {
        if ($this->role !== 'pelanggan' || $this->hasVerifiedEmail()) {
            return;
        }

        $this->notify(new VerifyEmailNotification);
    }

    public function isPelangganTerverifikasi(): bool
    {
        return $this->role === 'pelanggan' && $this->hasVerifiedEmail();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'id_user', 'id_user');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class, 'id_user', 'id_user');
    }

    public function cartItems()
    {
        return $this->hasMany(Cart::class, 'id_user', 'id_user');
    }
}
