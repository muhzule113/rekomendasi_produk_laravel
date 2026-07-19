<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UploadService
{
    public function handleUpload(array $file, int $id_user): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'pesan' => 'Upload gagal, coba lagi.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, config('app.allowed_extensions', ['csv', 'xlsx', 'xls']))) {
            return ['ok' => false, 'pesan' => 'Format file harus CSV, XLSX, atau XLS.'];
        }

        $ukuran_kb = round($file['size'] / 1024);
        $maxMb = config('app.max_upload_mb', 10);
        if ($ukuran_kb > $maxMb * 1024) {
            return ['ok' => false, 'pesan' => 'Ukuran file maksimal ' . $maxMb . 'MB.'];
        }

        $file_hash = hash_file('sha256', $file['tmp_name']);

        $exists = DB::table('data_uploads')->where('file_hash', $file_hash)->where('status', 'selesai')->exists();
        if ($exists) {
            return ['ok' => false, 'pesan' => 'File ini sudah pernah berhasil diupload sebelumnya.'];
        }

        $rawDir = storage_path('app/uploads/raw');
        if (!is_dir($rawDir)) mkdir($rawDir, 0755, true);

        $nama_disk = date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        $path_tujuan = $rawDir . '/' . $nama_disk;

        if (!move_uploaded_file($file['tmp_name'], $path_tujuan)) {
            return ['ok' => false, 'pesan' => 'Gagal menyimpan file ke server.'];
        }

        $id_upload = DB::table('data_uploads')->insertGetId([
            'id_user' => $id_user,
            'sumber' => 'transaksi',
            'nama_file_asli' => $file['name'],
            'nama_file_disk' => $nama_disk,
            'tipe_file' => $ext,
            'ukuran_kb' => $ukuran_kb,
            'path_file' => $path_tujuan,
            'file_hash' => $file_hash,
            'status' => 'menunggu',
            'uploaded_at' => now(),
        ]);

        $logsDir = storage_path('app/uploads/logs');
        if (!is_dir($logsDir)) mkdir($logsDir, 0755, true);
        $log_path = $logsDir . '/pipeline_' . $id_upload . '.log';

        $pythonBin = config('app.python_bin', 'python');
        $pipelineScript = config('app.pipeline_script', base_path('python/pipeline/pipeline_runner.py'));

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = sprintf(
                'start /B "" "%s" "%s" --upload_id %d > "%s" 2>&1',
                $pythonBin, $pipelineScript, $id_upload, $log_path
            );
            pclose(popen($cmd, 'r'));
        } else {
            $cmd = sprintf(
                '%s %s --upload_id %d > %s 2>&1 &',
                escapeshellarg($pythonBin), escapeshellarg($pipelineScript), $id_upload, escapeshellarg($log_path)
            );
            exec($cmd);
        }

        return [
            'ok' => true,
            'id_upload' => $id_upload,
            'nama_file' => $file['name'],
            'pesan' => 'File berhasil diupload. Preprocessing sedang berjalan...',
        ];
    }

    public function getStatus(int $id_upload): array
    {
        $result = DB::table('data_uploads')
            ->join('users', 'data_uploads.id_user', '=', 'users.id_user')
            ->leftJoin(DB::raw("(SELECT id_upload, COUNT(*) as sudah_diimport FROM upload_logs WHERE status_baris = 'imported' GROUP BY id_upload) as imported"),
                'data_uploads.id_upload', '=', 'imported.id_upload')
            ->where('data_uploads.id_upload', $id_upload)
            ->select('data_uploads.*', 'users.nama as nama_admin', DB::raw('COALESCE(imported.sudah_diimport, 0) as sudah_diimport'))
            ->first();

        return $result ? (array)$result : [];
    }

    public function getRiwayat(string $sumber = 'transaksi', int $limit = 20): array
    {
        return DB::table('data_uploads')
            ->join('users', 'data_uploads.id_user', '=', 'users.id_user')
            ->where('data_uploads.sumber', $sumber)
            ->select('data_uploads.*', 'users.nama as nama_admin')
            ->orderByDesc('data_uploads.uploaded_at')
            ->limit($limit)
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();
    }

    public function getLogBaris(int $id_upload, string $filter = 'all'): array
    {
        $query = DB::table('upload_logs')
            ->where('id_upload', $id_upload)
            ->orderBy('nomor_baris')
            ->limit(500);

        if ($filter !== 'all') {
            $query->where('status_baris', $filter);
        }

        return $query->get()->map(fn($r) => (array)$r)->toArray();
    }
}
