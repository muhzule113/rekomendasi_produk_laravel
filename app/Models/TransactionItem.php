<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $table = 'transaction_items';
    protected $primaryKey = 'id_item';
    public $timestamps = false;

    protected $fillable = [
        'id_transaction', 'id_product', 'nama_snapshot', 'harga_snapshot',
        'qty', 'harga', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'harga_snapshot' => 'decimal:2',
        ];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'id_transaction', 'id_transaction');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product', 'id_product');
    }
}
