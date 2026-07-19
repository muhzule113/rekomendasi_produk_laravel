<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataUpload extends Model
{
    protected $table = 'data_uploads';
    protected $primaryKey = 'id_upload';
    const CREATED_AT = 'uploaded_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_user', 'sumber', 'nama_file_asli', 'nama_file_disk', 'tipe_file',
        'ukuran_kb', 'path_file', 'file_hash', 'total_baris', 'baris_valid',
        'baris_invalid', 'baris_duplikat', 'baris_diimport', 'status',
        'pesan_error', 'kolom_mapping', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'kolom_mapping' => 'array',
            'processed_at' => 'datetime',
            'uploaded_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function logs()
    {
        return $this->hasMany(UploadLog::class, 'id_upload', 'id_upload');
    }
}
