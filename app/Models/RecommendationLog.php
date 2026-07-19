<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendationLog extends Model
{
    protected $table = 'recommendation_logs';
    protected $primaryKey = 'id_log';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = ['id_user', 'id_product', 'alasan', 'score'];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:6',
        ];
    }
}
