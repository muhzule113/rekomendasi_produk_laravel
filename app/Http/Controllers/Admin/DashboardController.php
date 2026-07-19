<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalProducts    = DB::table('products')->count();
        $totalCustomers   = DB::table('users')->where('role', 'pelanggan')->count();
        $totalTransactions = DB::table('transactions')->count();
        $totalRevenue = DB::table('transactions')->where('status_pembayaran', 'Dibayar')->sum('total');

        $transaksiBulanIni = DB::table('transactions')
            ->whereRaw('MONTH(tanggal) = MONTH(CURRENT_DATE())')
            ->whereRaw('YEAR(tanggal) = YEAR(CURRENT_DATE())')
            ->count();

        // Monthly chart data (6 months)
        $monthlyTransactions = DB::table('transactions')
            ->selectRaw('YEAR(tanggal) as year, MONTH(tanggal) as month, COUNT(*) as count')
            ->where('tanggal', '>=', now()->subMonths(6))
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($row) {
                $row->month_name = date('M', mktime(0, 0, 0, $row->month, 10));
                return $row;
            });

        $monthlyRevenue = DB::table('transactions')
            ->selectRaw('YEAR(tanggal) as year, MONTH(tanggal) as month, SUM(total) as total')
            ->where('status_pembayaran', 'Dibayar')
            ->where('tanggal', '>=', now()->subMonths(6))
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

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
            'user'
        ));
    }
}
