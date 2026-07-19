<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadLog extends Model
{
    protected $table = 'upload_logs';
    protected $primaryKey = 'id_log';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_upload', 'nomor_baris', 'status_baris', 'data_mentah',
        'data_bersih', 'id_transaction', 'keterangan',
    ];

    public function upload()
    {
        return $this->belongsTo(DataUpload::class, 'id_upload', 'id_upload');
    }
}
