<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecommenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SimilarityController extends Controller
{
    public function recalculate(RecommenderService $service): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        $start_time = microtime(true);

        try {
            // 1. Build matrix
            $matrix = $service->buildUserItemMatrix();
            if (empty($matrix)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Matriks kosong. Pastikan terdapat transaksi yang sudah Dibayar dan Selesai.',
                ]);
            }

            // 2. Calculate similarity
            $result = $service->calculateCosineSimilarity($matrix);

            if (isset($result['stats']['error'])) {
                return response()->json([
                    'status'  => false,
                    'message' => $result['stats']['error'],
                ]);
            }

            $similarities = $result['similarities'];
            $stats        = $result['stats'];

            if (empty($similarities)) {
                DB::table('cf_run_logs')->insert([
                    'started_at' => now()->subSeconds(max(0, microtime(true) - $start_time)),
                    'finished_at' => now(),
                    'total_users' => $stats['total_users'] ?? 0,
                    'total_products' => $stats['total_products_in_matrix'] ?? 0,
                    'total_pairs' => 0,
                    'coverage' => $stats['coverage_percentage'] ?? 0,
                    'max_score' => 0,
                    'avg_score' => 0,
                    'duration_seconds' => (int) round(microtime(true) - $start_time),
                    'status' => 'failed',
                    'error_message' => 'Hasil kosong; data similarity lama tidak diganti.',
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Hasil perhitungan similarity kosong (ambang co-occurrence / data tidak cukup). Data lama tidak diganti.',
                ]);
            }

            // 3. Save similarity (atomic; clear dirty only on success)
            $success = $service->saveSimilarity($similarities);

            $end_time = microtime(true);
            $execution_time = round($end_time - $start_time, 4);

            if ($success) {
                $stats['execution_time_seconds'] = $execution_time;

                DB::table('cf_run_logs')->insert([
                    'started_at' => now()->subSeconds((int) ceil($execution_time)),
                    'finished_at' => now(),
                    'total_users' => $stats['total_users'] ?? 0,
                    'total_products' => $stats['total_products_in_matrix'] ?? 0,
                    'total_pairs' => $stats['saved_pairs'] ?? 0,
                    'coverage' => $stats['coverage_percentage'] ?? 0,
                    'max_score' => $stats['max_score'] ?? 0,
                    'avg_score' => $stats['avg_score'] ?? 0,
                    'duration_seconds' => (int) round($execution_time),
                    'status' => 'success',
                ]);

                Log::info("[Similarity Calculation] Success. Time: {$execution_time}s, Users: {$stats['total_users']}, Pairs: {$stats['saved_pairs']}");

                return response()->json([
                    'status' => true,
                    'message' => 'Perhitungan cosine similarity berhasil dan disimpan.',
                    'summary' => $stats,
                ]);
            }

            throw new \Exception('Gagal menyimpan similarity ke database.');
        } catch (\Exception $e) {
            Log::error("[Similarity Calculation] Error: " . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan sistem saat memproses similarity.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
