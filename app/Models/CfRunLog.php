<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CfRunLog extends Model
{
    protected $table = 'cf_run_logs';
    protected $primaryKey = 'id_cf_run';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'started_at', 'finished_at', 'total_users', 'total_products',
        'total_pairs', 'coverage', 'max_score', 'avg_score',
        'duration_seconds', 'status', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'max_score' => 'decimal:6',
            'avg_score' => 'decimal:6',
            'coverage' => 'decimal:2',
        ];
    }
}
