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

        if (!$this->launchPipeline($id_upload, $log_path)) {
            return ['ok' => false, 'pesan' => 'Script pipeline tidak ditemukan. Periksa konfigurasi PYTHON_BIN / PIPELINE_SCRIPT.'];
        }

        return [
            'ok' => true,
            'id_upload' => $id_upload,
            'nama_file' => $file['name'],
            'pesan' => 'File berhasil diupload. Preprocessing sedang berjalan...',
        ];
    }

    public function handleProdukUpload(array $file, int $id_user): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'pesan' => 'Upload gagal, coba lagi.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            return ['ok' => false, 'pesan' => 'Format file harus CSV.'];
        }

        $ukuran_kb = round($file['size'] / 1024);
        $maxMb = config('app.max_upload_mb', 10);
        if ($ukuran_kb > $maxMb * 1024) {
            return ['ok' => false, 'pesan' => 'Ukuran file maksimal ' . $maxMb . 'MB.'];
        }

        $file_hash = hash_file('sha256', $file['tmp_name']);
        $exists = DB::table('data_uploads')
            ->where('file_hash', $file_hash)
            ->where('sumber', 'produk')
            ->where('status', 'selesai')
            ->exists();
        if ($exists) {
            return ['ok' => false, 'pesan' => 'File ini sudah pernah berhasil diupload sebelumnya.'];
        }

        $rawDir = storage_path('app/uploads/raw');
        $doneDir = storage_path('app/uploads/processed');
        if (!is_dir($rawDir)) mkdir($rawDir, 0755, true);
        if (!is_dir($doneDir)) mkdir($doneDir, 0755, true);

        $nama_disk = date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        $path_tujuan = $rawDir . '/' . $nama_disk;

        if (!move_uploaded_file($file['tmp_name'], $path_tujuan)) {
            return ['ok' => false, 'pesan' => 'Gagal menyimpan file ke server.'];
        }

        $id_upload = DB::table('data_uploads')->insertGetId([
            'id_user' => $id_user,
            'sumber' => 'produk',
            'nama_file_asli' => $file['name'],
            'nama_file_disk' => $nama_disk,
            'tipe_file' => $ext,
            'ukuran_kb' => $ukuran_kb,
            'path_file' => $path_tujuan,
            'file_hash' => $file_hash,
            'status' => 'memproses',
            'uploaded_at' => now(),
        ]);

        try {
            $result = $this->importProdukCsv($path_tujuan, $id_upload);
        } catch (\Throwable $e) {
            DB::table('data_uploads')->where('id_upload', $id_upload)->update([
                'status' => 'gagal',
                'pesan_error' => $e->getMessage(),
                'processed_at' => now(),
            ]);
            Log::error('Product import failed', ['id_upload' => $id_upload, 'error' => $e->getMessage()]);

            return ['ok' => false, 'pesan' => 'Gagal memproses CSV produk: ' . $e->getMessage()];
        }

        rename($path_tujuan, $doneDir . '/' . $nama_disk);
        DB::table('data_uploads')->where('id_upload', $id_upload)->update([
            'path_file' => $doneDir . '/' . $nama_disk,
            'total_baris' => $result['total_baris'],
            'baris_valid' => $result['baris_valid'],
            'baris_invalid' => $result['baris_invalid'],
            'baris_diimport' => $result['baris_valid'],
            'status' => 'selesai',
            'processed_at' => now(),
        ]);

        $pesan = sprintf(
            'Selesai: %d produk diproses (%d baru, %d diupdate), %d gagal.',
            $result['baris_valid'],
            $result['inserted'],
            $result['updated'],
            $result['baris_invalid']
        );

        return [
            'ok' => true,
            'id_upload' => $id_upload,
            'nama_file' => $file['name'],
            'pesan' => $pesan,
            'baris_valid' => $result['baris_valid'],
            'baris_invalid' => $result['baris_invalid'],
            'errors' => $result['errors'],
        ];
    }

    private function importProdukCsv(string $path, int $id_upload): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new \RuntimeException('Tidak bisa membaca file CSV.');
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new \RuntimeException('File CSV kosong atau header tidak valid.');
        }

        $colMap = $this->mapProdukColumns($header);
        foreach (['nama_product', 'harga', 'stok'] as $required) {
            if (!isset($colMap[$required])) {
                fclose($handle);
                throw new \RuntimeException("Kolom wajib tidak ditemukan: {$required}");
            }
        }

        $categories = DB::table('categories')->pluck('id_category', 'nama_category')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim($name)) => $id])
            ->all();
        $defaultCategoryId = (int) DB::table('categories')->orderBy('id_category')->value('id_category');

        $total = 0;
        $valid = 0;
        $invalid = 0;
        $inserted = 0;
        $updated = 0;
        $errors = [];
        $logs = [];
        $nomorBaris = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $nomorBaris++;
            if ($this->isEmptyCsvRow($row)) {
                continue;
            }

            $total++;
            $data = $this->rowToAssoc($header, $row);
            $nama = trim((string) ($data[$colMap['nama_product']] ?? ''));
            $harga = $this->parseAngka($data[$colMap['harga']] ?? null);
            $stok = $this->parseAngka($data[$colMap['stok']] ?? null);
            $namaCategory = isset($colMap['nama_category'])
                ? trim((string) ($data[$colMap['nama_category']] ?? ''))
                : '';
            $deskripsi = isset($colMap['deskripsi'])
                ? trim((string) ($data[$colMap['deskripsi']] ?? ''))
                : null;
            $status = isset($colMap['status'])
                ? strtolower(trim((string) ($data[$colMap['status']] ?? 'aktif')))
                : 'aktif';

            $rowErrors = [];
            if ($nama === '') $rowErrors[] = 'nama_product wajib diisi';
            if ($harga === null || $harga < 0) $rowErrors[] = 'harga tidak valid';
            if ($stok === null || $stok < 0 || floor($stok) != $stok) $rowErrors[] = 'stok tidak valid';
            if (!in_array($status, ['aktif', 'nonaktif'], true)) $rowErrors[] = 'status harus aktif/nonaktif';

            $idCategory = $this->resolveCategoryId($namaCategory, $categories, $defaultCategoryId);

            if ($rowErrors) {
                $invalid++;
                $keterangan = implode('; ', $rowErrors);
                $errors[] = ['baris' => $nomorBaris, 'pesan' => $keterangan];
                $logs[] = [
                    'id_upload' => $id_upload,
                    'nomor_baris' => $nomorBaris,
                    'status_baris' => 'invalid',
                    'data_mentah' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'keterangan' => $keterangan,
                    'created_at' => now(),
                ];
                continue;
            }

            $payload = [
                'nama_product' => $nama,
                'id_category' => $idCategory,
                'harga' => $harga,
                'stok' => (int) $stok,
                'deskripsi' => $deskripsi ?: null,
                'status' => $status,
            ];

            $existing = DB::table('products')->where('nama_product', $nama)->first();
            if ($existing) {
                DB::table('products')->where('id_product', $existing->id_product)->update($payload);
                $updated++;
            } else {
                DB::table('products')->insert(array_merge($payload, ['created_at' => now()]));
                $inserted++;
            }

            $valid++;
            $logs[] = [
                'id_upload' => $id_upload,
                'nomor_baris' => $nomorBaris,
                'status_baris' => 'imported',
                'data_mentah' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'keterangan' => $existing ? 'Produk diupdate' : 'Produk baru ditambahkan',
                'created_at' => now(),
            ];
        }

        fclose($handle);

        if ($logs) {
            DB::table('upload_logs')->insert($logs);
        }

        return [
            'total_baris' => $total,
            'baris_valid' => $valid,
            'baris_invalid' => $invalid,
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    private function mapProdukColumns(array $header): array
    {
        $aliases = [
            'nama_product' => ['nama_product', 'nama produk', 'product_name', 'nama barang'],
            'harga' => ['harga', 'price'],
            'stok' => ['stok', 'stock', 'qty', 'jumlah'],
            'nama_category' => ['nama_category', 'nama category', 'kategori', 'category', 'nama_kategori'],
            'deskripsi' => ['deskripsi', 'description', 'desc'],
            'status' => ['status'],
        ];

        $cols = [];
        foreach ($header as $i => $name) {
            $cols[mb_strtolower(trim($name))] = trim($name);
        }

        $map = [];
        foreach ($aliases as $field => $names) {
            foreach ($names as $alias) {
                if (isset($cols[$alias])) {
                    $map[$field] = $cols[$alias];
                    break;
                }
            }
        }

        return $map;
    }

    private function resolveCategoryId(string $namaCategory, array &$categories, int &$defaultCategoryId): int
    {
        if ($namaCategory === '') {
            if ($defaultCategoryId) {
                return $defaultCategoryId;
            }
            $namaCategory = 'Umum';
        }

        $key = mb_strtolower($namaCategory);
        if (isset($categories[$key])) {
            return (int) $categories[$key];
        }

        $id = (int) DB::table('categories')->insertGetId(['nama_category' => $namaCategory]);
        $categories[$key] = $id;
        if (!$defaultCategoryId) {
            $defaultCategoryId = $id;
        }

        return $id;
    }

    private function rowToAssoc(array $header, array $row): array
    {
        $data = [];
        foreach ($header as $i => $col) {
            $data[trim($col)] = $row[$i] ?? '';
        }

        return $data;
    }

    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseAngka(mixed $val): ?float
    {
        if ($val === null) {
            return null;
        }

        $val = trim((string) $val);
        if ($val === '') {
            return null;
        }

        $val = str_ireplace(['rp', ' '], '', $val);
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);

        return is_numeric($val) ? (float) $val : null;
    }

    private function launchPipeline(int $id_upload, string $log_path): bool
    {
        $pythonBin = config('app.python_bin') ?: 'python';
        $pipelineScript = config('app.pipeline_script') ?: base_path('python/pipeline/pipeline_runner.py');

        if (!is_file($pipelineScript)) {
            DB::table('data_uploads')->where('id_upload', $id_upload)->update([
                'status' => 'gagal',
                'pesan_error' => 'Script pipeline tidak ditemukan: ' . $pipelineScript,
                'processed_at' => now(),
            ]);
            Log::error('Pipeline script not found', ['path' => $pipelineScript, 'id_upload' => $id_upload]);

            return false;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = sprintf(
                'cmd /c start /B "" %s %s --upload_id %d >> %s 2>&1',
                escapeshellarg($pythonBin),
                escapeshellarg($pipelineScript),
                $id_upload,
                escapeshellarg($log_path)
            );
            pclose(popen($cmd, 'r'));
        } else {
            $cmd = sprintf(
                '%s %s --upload_id %d >> %s 2>&1 &',
                escapeshellarg($pythonBin),
                escapeshellarg($pipelineScript),
                $id_upload,
                escapeshellarg($log_path)
            );
            exec($cmd);
        }

        return true;
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

    public function deleteUpload(int $id_upload): array
    {
        $upload = DB::table('data_uploads')->where('id_upload', $id_upload)->first();
        if (!$upload) {
            return ['ok' => false, 'pesan' => 'Riwayat upload tidak ditemukan.'];
        }

        if (in_array($upload->status, ['menunggu', 'memproses'], true)) {
            return ['ok' => false, 'pesan' => 'Upload masih diproses. Tunggu selesai atau gagal sebelum dihapus.'];
        }

        if (!empty($upload->path_file) && is_file($upload->path_file)) {
            @unlink($upload->path_file);
        }

        $logPath = storage_path('app/uploads/logs/pipeline_' . $id_upload . '.log');
        if (is_file($logPath)) {
            @unlink($logPath);
        }

        DB::table('data_uploads')->where('id_upload', $id_upload)->delete();

        return ['ok' => true, 'pesan' => 'Riwayat upload berhasil dihapus.'];
    }
}
