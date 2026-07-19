<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'transactions';
    protected $primaryKey = 'id_transaction';
    const CREATED_AT = 'tanggal';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'id_user', 'id_upload', 'kode_transaksi', 'tanggal', 'subtotal',
        'ongkir', 'diskon', 'total', 'alamat_pengiriman', 'nama_penerima',
        'no_hp_penerima', 'metode_pembayaran', 'status_pembayaran',
        'status_pesanan', 'sumber_data', 'midtrans_order_id', 'snap_token',
        'payment_type', 'fraud_status', 'payment_status', 'paid_at',
        'expired_at', 'payment_payload',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'ongkir' => 'decimal:2',
            'diskon' => 'decimal:2',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
            'payment_payload' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class, 'id_transaction', 'id_transaction');
    }

    public function statusLogs()
    {
        return $this->hasMany(TransactionStatusLog::class, 'id_transaction', 'id_transaction');
    }
}
