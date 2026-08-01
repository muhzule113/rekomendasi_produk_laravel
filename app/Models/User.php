<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $table = 'users';
    protected $primaryKey = 'id_user';
    public $timestamps = false;

    protected $fillable = [
        'nama', 'email', 'no_hp', 'alamat', 'password', 'role', 'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
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
