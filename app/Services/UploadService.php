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

    public function handleUploadProduk(array $file, int $id_user): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'pesan' => 'Upload gagal, coba lagi.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            return ['ok' => false, 'pesan' => 'Format file produk harus CSV.'];
        }

        $ukuran_kb = round($file['size'] / 1024);
        if ($ukuran_kb > 10240) {
            return ['ok' => false, 'pesan' => 'Ukuran file maksimal 10MB.'];
        }

        $file_hash = hash_file('sha256', $file['tmp_name']);

        $rawDir = storage_path('app/uploads/raw');
        if (!is_dir($rawDir)) mkdir($rawDir, 0755, true);

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

        // Proses CSV langsung di PHP
        $handle = fopen($path_tujuan, 'r');
        if (!$handle) {
            DB::table('data_uploads')->where('id_upload', $id_upload)->update(['status' => 'gagal', 'pesan_error' => 'Gagal membaca file']);
            return ['ok' => false, 'pesan' => 'Gagal membaca file CSV.'];
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            DB::table('data_uploads')->where('id_upload', $id_upload)->update(['status' => 'gagal', 'pesan_error' => 'File CSV kosong']);
            return ['ok' => false, 'pesan' => 'File CSV kosong.'];
        }

        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        // Map kolom
        $colMap = [];
        $aliases = [
            'nama_product' => ['nama_product', 'nama produk', 'product_name', 'nama barang', 'nama'],
            'harga' => ['harga', 'harga_satuan', 'price', 'harga satuan'],
            'stok' => ['stok', 'stock', 'qty', 'jumlah', 'quantity'],
            'nama_category' => ['nama_category', 'category', 'kategori', 'nama kategori', 'nama_kategori'],
            'deskripsi' => ['deskripsi', 'description', 'deskripsi produk'],
            'status' => ['status', 'status_produk', 'produk_status'],
        ];

        foreach ($aliases as $field => $aliasList) {
            foreach ($aliasList as $alias) {
                $idx = array_search($alias, $header);
                if ($idx !== false) {
                    $colMap[$field] = $idx;
                    break;
                }
            }
        }

        if (!isset($colMap['nama_product'])) {
            fclose($handle);
            DB::table('data_uploads')->where('id_upload', $id_upload)->update(['status' => 'gagal', 'pesan_error' => 'Kolom nama_product tidak ditemukan']);
            return ['ok' => false, 'pesan' => 'Kolom wajib "nama_product" tidak ditemukan di CSV.'];
        }
        if (!isset($colMap['harga'])) {
            fclose($handle);
            DB::table('data_uploads')->where('id_upload', $id_upload)->update(['status' => 'gagal', 'pesan_error' => 'Kolom harga tidak ditemukan']);
            return ['ok' => false, 'pesan' => 'Kolom wajib "harga" tidak ditemukan di CSV.'];
        }
        if (!isset($colMap['stok'])) {
            fclose($handle);
            DB::table('data_uploads')->where('id_upload', $id_upload)->update(['status' => 'gagal', 'pesan_error' => 'Kolom stok tidak ditemukan']);
            return ['ok' => false, 'pesan' => 'Kolom wajib "stok" tidak ditemukan di CSV.'];
        }

        $baris_valid = 0;
        $baris_invalid = 0;
        $logs = [];
        $nomor_baris = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $nomor_baris++;
            $data_mentah = json_encode($row, JSON_UNESCAPED_UNICODE);

            try {
                $nama_product = trim($row[$colMap['nama_product']] ?? '');
                $harga = trim($row[$colMap['harga']] ?? '');
                $stok = trim($row[$colMap['stok']] ?? '');

                if (empty($nama_product)) {
                    throw new \Exception('Nama produk kosong');
                }
                if (!is_numeric($harga) || (float)$harga < 0) {
                    throw new \Exception('Harga tidak valid: ' . $harga);
                }
                if (!is_numeric($stok) || (int)$stok < 0) {
                    throw new \Exception('Stok tidak valid: ' . $stok);
                }

                // Optional: category
                $id_category = null;
                if (isset($colMap['nama_category'])) {
                    $nama_category = trim($row[$colMap['nama_category']] ?? '');
                    if (!empty($nama_category)) {
                        $cat = DB::table('categories')->where('nama_category', $nama_category)->first();
                        if (!$cat) {
                            $id_category = DB::table('categories')->insertGetId(['nama_category' => $nama_category]);
                        } else {
                            $id_category = $cat->id_category;
                        }
                    }
                }

                // Optional: deskripsi
                $deskripsi = isset($colMap['deskripsi']) ? trim($row[$colMap['deskripsi']] ?? '') : null;

                // Optional: status
                $status = 'aktif';
                if (isset($colMap['status'])) {
                    $s = strtolower(trim($row[$colMap['status']] ?? ''));
                    if (in_array($s, ['nonaktif', 'non-aktif', 'tidak aktif', '0', 'false'])) {
                        $status = 'nonaktif';
                    }
                }

                // Upsert produk by nama_product
                $existing = DB::table('products')->where('nama_product', $nama_product)->first();
                $dataUpdate = [
                    'harga' => (float)$harga,
                    'stok' => (int)$stok,
                    'deskripsi' => $deskripsi,
                    'status' => $status,
                ];
                if ($id_category) {
                    $dataUpdate['id_category'] = $id_category;
                }

                if ($existing) {
                    DB::table('products')->where('id_product', $existing->id_product)->update($dataUpdate);
                    $id_product = $existing->id_product;
                } else {
                    $dataUpdate['nama_product'] = $nama_product;
                    $id_product = DB::table('products')->insertGetId($dataUpdate);
                }

                $baris_valid++;
                $logs[] = [
                    'id_upload' => $id_upload,
                    'nomor_baris' => $nomor_baris,
                    'status_baris' => 'imported',
                    'data_mentah' => $data_mentah,
                    'data_bersih' => json_encode(['id_product' => $id_product, 'nama_product' => $nama_product], JSON_UNESCAPED_UNICODE),
                    'id_transaction' => null,
                    'keterangan' => $existing ? 'Produk diupdate' : 'Produk baru ditambahkan',
                ];

            } catch (\Exception $e) {
                $baris_invalid++;
                $logs[] = [
                    'id_upload' => $id_upload,
                    'nomor_baris' => $nomor_baris,
                    'status_baris' => 'invalid',
                    'data_mentah' => $data_mentah,
                    'data_bersih' => null,
                    'id_transaction' => null,
                    'keterangan' => $e->getMessage(),
                ];
            }
        }
        fclose($handle);

        $total_baris = $baris_valid + $baris_invalid;

        // Simpan logs
        DB::table('upload_logs')->insert($logs);

        // Update status upload
        DB::table('data_uploads')->where('id_upload', $id_upload)->update([
            'status' => 'selesai',
            'total_baris' => $total_baris,
            'baris_valid' => $baris_valid,
            'baris_invalid' => $baris_invalid,
            'baris_diimport' => $baris_valid,
            'processed_at' => now(),
        ]);

        return [
            'ok' => true,
            'id_upload' => $id_upload,
            'nama_file' => $file['name'],
            'pesan' => 'Upload produk selesai! ' . $baris_valid . ' produk diimport, ' . $baris_invalid . ' gagal.',
            'total_baris' => $total_baris,
            'baris_valid' => $baris_valid,
            'baris_invalid' => $baris_invalid,
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
