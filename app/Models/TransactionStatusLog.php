<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionStatusLog extends Model
{
    protected $table = 'transaction_status_logs';
    protected $primaryKey = 'id_log';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_transaction', 'field_changed', 'old_value', 'new_value', 'diubah_oleh',
    ];
}
