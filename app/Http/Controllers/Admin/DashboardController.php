<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalProducts = DB::table('products')->count();
        $totalCustomers = DB::table('users')->where('role', 'pelanggan')->count();
        $totalTransactions = DB::table('transactions')->count();
        $totalRevenue = DB::table('transactions')->where('status_pembayaran', 'Dibayar')->sum('total');
        $startOfMonth = now()->startOfMonth();
        $startOfLastMonth = now()->subMonthNoOverflow()->startOfMonth();

        $previousTotalProducts = DB::table('products')
            ->where('created_at', '<', $startOfMonth)
            ->count();
        $previousTotalCustomers = DB::table('users')
            ->where('role', 'pelanggan')
            ->where('created_at', '<', $startOfMonth)
            ->count();
        $currentMonthRevenue = DB::table('transactions')
            ->where('status_pembayaran', 'Dibayar')
            ->where('tanggal', '>=', $startOfMonth)
            ->sum('total');
        $lastMonthRevenue = DB::table('transactions')
            ->where('status_pembayaran', 'Dibayar')
            ->where('tanggal', '>=', $startOfLastMonth)
            ->where('tanggal', '<', $startOfMonth)
            ->sum('total');

        $dashboardTrends = [
            'products' => $this->buildTrend($totalProducts, $previousTotalProducts),
            'customers' => $this->buildTrend($totalCustomers, $previousTotalCustomers),
            'revenue' => $this->buildTrend((float) $currentMonthRevenue, (float) $lastMonthRevenue),
        ];

        $transaksiBulanIni = DB::table('transactions')
            ->whereBetween('tanggal', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        // Anchor the chart to the latest available transaction so imported/historical
        // datasets remain visible even when they do not contain current-month data.
        $latestTransactionDate = DB::table('transactions')->max('tanggal');
        $chartEndMonth = $latestTransactionDate
            ? Carbon::parse($latestTransactionDate)->startOfMonth()
            : now()->startOfMonth();
        $chartStartMonth = $chartEndMonth->copy()->subMonths(5);

        $monthlyTransactions = collect(range(0, 5))->map(function (int $offset) use ($chartStartMonth) {
            $month = $chartStartMonth->copy()->addMonths($offset);
            $stats = DB::table('transactions')
                ->whereBetween('tanggal', [
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth(),
                ])
                ->selectRaw(
                    'COUNT(*) as count, COALESCE(SUM(CASE WHEN status_pembayaran = ? THEN total ELSE 0 END), 0) as total',
                    ['Dibayar']
                )
                ->first();

            return (object) [
                'year' => $month->year,
                'month' => $month->month,
                'month_name' => $month->format('M'),
                'count' => (int) $stats->count,
                'total' => (float) $stats->total,
            ];
        });

        $monthlyRevenue = $monthlyTransactions->map(fn ($month) => (object) [
            'year' => $month->year,
            'month' => $month->month,
            'total' => $month->total,
        ]);

        // Category popularity
        $kategoriPopuler = DB::table('transaction_items')
            ->join('products', 'transaction_items.id_product', '=', 'products.id_product')
            ->join('categories', 'products.id_category', '=', 'categories.id_category')
            ->selectRaw('categories.nama_category, SUM(transaction_items.qty) as total_qty')
            ->groupBy('categories.id_category', 'categories.nama_category')
            ->orderByDesc('total_qty')
            ->limit(8)
            ->get();

        // Recent transactions
        $recentTransactions = DB::table('transactions')
            ->join('users', 'transactions.id_user', '=', 'users.id_user')
            ->select('transactions.id_transaction', 'transactions.total', 'transactions.status_pembayaran', 'users.nama', 'transactions.tanggal as created_at')
            ->orderByDesc('transactions.tanggal')
            ->limit(5)
            ->get();

        $user = auth()->user();

        return view('admin.dashboard', compact(
            'totalProducts',
            'totalCustomers',
            'totalTransactions',
            'totalRevenue',
            'transaksiBulanIni',
            'monthlyTransactions',
            'monthlyRevenue',
            'kategoriPopuler',
            'recentTransactions',
            'user',
            'dashboardTrends'
        ));
    }

    private function buildTrend(float $current, float $previous): array
    {
        if ($previous == 0.0) {
            $percentage = $current > 0.0 ? 100.0 : 0.0;
        } else {
            $percentage = (($current - $previous) / abs($previous)) * 100;
        }

        $direction = $percentage < 0 ? 'down' : ($percentage > 0 ? 'up' : 'flat');
        $colors = [
            'up' => '#10b981',
            'down' => '#ef4444',
            'flat' => '#64748b',
        ];

        $roundedPercentage = round($percentage, 1);
        $progress = round(min(abs($percentage), 100), 1);

        return [
            'percentage' => $roundedPercentage,
            'display' => ($percentage > 0 ? '+' : '').number_format($roundedPercentage, 1).'%',
            'circle_display' => number_format($progress, 0).'%',
            'direction' => $direction,
            'label' => $direction === 'down' ? 'Turun' : ($direction === 'up' ? 'Naik' : 'Tetap'),
            'icon' => $direction === 'down' ? 'fa-arrow-trend-down' : ($direction === 'up' ? 'fa-arrow-trend-up' : 'fa-minus'),
            'color' => $colors[$direction],
            'progress' => $progress,
        ];
    }
}
