<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $table = 'product_reviews';
    protected $primaryKey = 'id_review';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = ['id_product', 'id_user', 'rating', 'komentar'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product', 'id_product');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
