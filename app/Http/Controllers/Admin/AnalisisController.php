<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RecommenderService;
use Illuminate\Support\Facades\DB;

class AnalisisController extends Controller
{
    public function index(RecommenderService $recommender)
    {
        $total_produk = DB::table('products')->where('status', 'aktif')->count();
        $total_trans = DB::table('transactions')
            ->where('status_pesanan', 'Selesai')
            ->where('status_pembayaran', 'Dibayar')
            ->count();
        $total_pelanggan = DB::table('users')->where('role', 'pelanggan')->where('status', 'aktif')->count();
        $total_rekomendasi = DB::table('recommendation_logs')->count();

        $matrix_products = DB::table('product_similarity')->distinct()->count('product_a');
        $total_pairs = DB::table('product_similarity')->whereColumn('product_a', '<', 'product_b')->count();
        $last_updated = DB::table('product_similarity')->max('updated_at');
        $ideal_pairs = $matrix_products > 1 ? ($matrix_products * ($matrix_products - 1)) / 2 : 0;
        $pair_coverage = $ideal_pairs > 0 ? round(($total_pairs / $ideal_pairs) * 100, 2) : 0;
        $coverage = $pair_coverage; // alias untuk blade lama

        $driver = DB::getDriverName();
        $stdExpr = $driver === 'sqlite' ? '0 as std_s' : 'STDDEV(score) as std_s';

        $simStats = DB::table('product_similarity')
            ->whereColumn('product_a', '<', 'product_b')
            ->selectRaw("MAX(score) as max_s, MIN(score) as min_s, AVG(score) as avg_s, {$stdExpr}")
            ->first();

        $max_score = $simStats->max_s ?? 0;
        $min_score = $simStats->min_s ?? 0;
        $avg_score = $simStats->avg_s ?? 0;
        $std_score = $simStats->std_s ?? 0;

        $source_stats = DB::table('product_similarity')
            ->whereColumn('product_a', '<', 'product_b')
            ->selectRaw('source, COUNT(*) as cnt, AVG(score) as avg_score')
            ->groupBy('source')
            ->orderByDesc('cnt')
            ->get();

        $top_recommended = DB::table('product_similarity as ps')
            ->join('products as p', 'ps.product_b', '=', 'p.id_product')
            ->join('categories as c', 'p.id_category', '=', 'c.id_category')
            ->whereColumn('ps.product_a', '<', 'ps.product_b')
            ->select(
                'p.nama_product',
                'c.nama_category',
                DB::raw('COUNT(*) as total_rek'),
                DB::raw('ROUND(AVG(ps.score), 4) as avg_score_rek')
            )
            ->groupBy('p.id_product', 'p.nama_product', 'c.nama_category')
            ->orderByDesc('total_rek')
            ->limit(10)
            ->get();

        $cf_runs = DB::table('cf_run_logs')->orderByDesc('started_at')->limit(5)->get();

        $evaluation_logs = collect();
        if (DB::getSchemaBuilder()->hasTable('evaluation_logs')) {
            $evaluation_logs = DB::table('evaluation_logs')
                ->orderByDesc('evaluated_at')
                ->orderBy('k_value')
                ->limit(10)
                ->get();
        }

        $recommendation_dirty = $recommender->isRecommendationDirty();
        $min_co_occurrence = $recommender->minCoOccurrence();

        $distRanges = DB::table('product_similarity')
            ->whereColumn('product_a', '<', 'product_b')
            ->selectRaw('
                SUM(CASE WHEN score > 0 AND score < 0.15 THEN 1 ELSE 0 END) as r_low,
                SUM(CASE WHEN score >= 0.15 AND score < 0.30 THEN 1 ELSE 0 END) as r_med_low,
                SUM(CASE WHEN score >= 0.30 AND score < 0.50 THEN 1 ELSE 0 END) as r_med,
                SUM(CASE WHEN score >= 0.50 AND score < 0.70 THEN 1 ELSE 0 END) as r_med_high,
                SUM(CASE WHEN score >= 0.70 AND score <= 1.00 THEN 1 ELSE 0 END) as r_high
            ')
            ->first();

        $chartLabels = json_encode([
            'Rendah (0–0.15)',
            'Sedang Rendah (0.15–0.30)',
            'Sedang (0.30–0.50)',
            'Sedang Tinggi (0.50–0.70)',
            'Tinggi (0.70–1.00)',
        ]);

        $chartData = json_encode([
            (int) ($distRanges->r_low ?? 0),
            (int) ($distRanges->r_med_low ?? 0),
            (int) ($distRanges->r_med ?? 0),
            (int) ($distRanges->r_med_high ?? 0),
            (int) ($distRanges->r_high ?? 0),
        ]);

        $rek_log_summary = DB::table('recommendation_logs')
            ->selectRaw('DATE(created_at) as tgl, COUNT(*) as jumlah, ROUND(AVG(score), 4) as avg_score')
            ->groupBy('tgl')
            ->orderByDesc('tgl')
            ->limit(30)
            ->get();

        $similarities = DB::table('product_similarity as ps')
            ->join('products as p1', 'ps.product_a', '=', 'p1.id_product')
            ->join('products as p2', 'ps.product_b', '=', 'p2.id_product')
            ->whereColumn('ps.product_a', '<', 'ps.product_b')
            ->select(
                'p1.nama_product as p1_name',
                'p2.nama_product as p2_name',
                'p1.id_product as p1_id',
                'p2.id_product as p2_id',
                'ps.score',
                'ps.co_occurrence',
                'ps.source',
                'ps.updated_at'
            )
            ->orderByDesc('ps.score')
            ->paginate(15);

        $currentPage = $similarities->currentPage();
        $totalPages = $similarities->lastPage();
        $count_pairs = $similarities->total();
        $limit_sim = 15;
        $offset_sim = ($currentPage - 1) * $limit_sim;

        return view('admin.analisis', compact(
            'total_produk',
            'total_trans',
            'total_pelanggan',
            'total_rekomendasi',
            'matrix_products',
            'total_pairs',
            'last_updated',
            'ideal_pairs',
            'coverage',
            'pair_coverage',
            'max_score',
            'min_score',
            'avg_score',
            'std_score',
            'source_stats',
            'top_recommended',
            'cf_runs',
            'evaluation_logs',
            'recommendation_dirty',
            'min_co_occurrence',
            'rek_log_summary',
            'similarities',
            'currentPage',
            'totalPages',
            'count_pairs',
            'limit_sim',
            'offset_sim',
            'chartLabels',
            'chartData'
        ));
    }
}
