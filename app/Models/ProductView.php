<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductView extends Model
{
    protected $table = 'product_views';
    protected $primaryKey = 'id_view';
    const CREATED_AT = 'viewed_at';
    const UPDATED_AT = null;

    protected $fillable = ['id_product', 'id_user', 'ip_address'];
}
