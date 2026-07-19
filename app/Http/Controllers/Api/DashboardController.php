<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type', '');

        if ($type === 'monthly_transactions') {
            $results = DB::table('transactions')
                ->select(DB::raw("DATE_FORMAT(tanggal, '%Y-%m') as bulan"), DB::raw('COUNT(*) as total_transaksi'))
                ->groupBy('bulan')
                ->orderBy('bulan')
                ->limit(6)
                ->get();

            $labels = [];
            $values = [];
            foreach ($results as $row) {
                $labels[] = date('F Y', strtotime($row->bulan . '-01'));
                $values[] = $row->total_transaksi;
            }

            return response()->json(['labels' => $labels, 'values' => $values]);
        }

        if ($type === 'top_categories') {
            $results = DB::table('transaction_items as ti')
                ->join('products as p', 'ti.id_product', '=', 'p.id_product')
                ->join('categories as c', 'p.id_category', '=', 'c.id_category')
                ->select('c.nama_category', DB::raw('SUM(ti.qty) as total_terjual'))
                ->groupBy('c.id_category')
                ->orderByDesc('total_terjual')
                ->limit(5)
                ->get();

            $labels = [];
            $values = [];
            foreach ($results as $row) {
                $labels[] = $row->nama_category;
                $values[] = $row->total_terjual;
            }

            return response()->json(['labels' => $labels, 'values' => $values]);
        }

        // Default: summary stats + monthly chart
        $totalProducts    = DB::table('products')->where('status', 'aktif')->count();
        $totalCustomers   = DB::table('users')->where('role', 'pelanggan')->count();
        $totalTransactions = DB::table('transactions')->count();
        $totalRevenue     = DB::table('transactions')
            ->where('status_pembayaran', 'Dibayar')
            ->sum('total');

        // Monthly chart data
        $monthlyResults = DB::table('transactions')
            ->select(DB::raw("DATE_FORMAT(tanggal, '%Y-%m') as bulan"), DB::raw('COUNT(*) as total_transaksi'))
            ->groupBy('bulan')
            ->orderBy('bulan')
            ->limit(6)
            ->get();

        $monthlyLabels = [];
        $monthlyValues = [];
        foreach ($monthlyResults as $row) {
            $monthlyLabels[] = date('F Y', strtotime($row->bulan . '-01'));
            $monthlyValues[] = $row->total_transaksi;
        }

        return response()->json([
            'status'              => true,
            'total_products'      => $totalProducts,
            'total_customers'     => $totalCustomers,
            'total_transactions'  => $totalTransactions,
            'total_revenue'       => (float) $totalRevenue,
            'chart' => [
                'labels' => $monthlyLabels,
                'values' => $monthlyValues,
            ],
        ]);
    }
}
