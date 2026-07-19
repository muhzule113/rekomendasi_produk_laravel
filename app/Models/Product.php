<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'id_product';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_category', 'nama_product', 'deskripsi', 'harga', 'stok',
        'foto', 'rating', 'terjual', 'status',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'rating' => 'decimal:2',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'id_category', 'id_category');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class, 'id_product', 'id_product');
    }

    public function similarityA()
    {
        return $this->hasMany(ProductSimilarity::class, 'product_a', 'id_product');
    }

    public function similarityB()
    {
        return $this->hasMany(ProductSimilarity::class, 'product_b', 'id_product');
    }
}
