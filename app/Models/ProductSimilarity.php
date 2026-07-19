<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSimilarity extends Model
{
    protected $table = 'product_similarity';
    protected $primaryKey = 'id_similarity';
    const CREATED_AT = null;
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'product_a', 'product_b', 'score', 'co_occurrence', 'source',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:6',
        ];
    }

    public function productA()
    {
        return $this->belongsTo(Product::class, 'product_a', 'id_product');
    }

    public function productB()
    {
        return $this->belongsTo(Product::class, 'product_b', 'id_product');
    }
}
