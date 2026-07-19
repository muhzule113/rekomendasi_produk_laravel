<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        $tanggalAwal  = $request->get('tanggal_awal');
        $tanggalAkhir = $request->get('tanggal_akhir');
        $export       = $request->get('export');

        $query = DB::table('transactions as t')
            ->join('users as u', 't.id_user', '=', 'u.id_user')
            ->select('t.id_transaction', 't.tanggal', 'u.nama', 't.metode_pembayaran', 't.status_pesanan', 't.total');

        if ($tanggalAwal) {
            $query->whereRaw('DATE(t.tanggal) >= ?', [$tanggalAwal]);
        }
        if ($tanggalAkhir) {
            $query->whereRaw('DATE(t.tanggal) <= ?', [$tanggalAkhir]);
        }

        $transactions = $query->orderByDesc('t.id_transaction')->get();

        $grandTotal = $transactions->sum('total');

        // Totals by period
        $periodTotals = DB::table('transactions as t')
            ->selectRaw("DATE_FORMAT(t.tanggal, '%Y-%m') as period, COUNT(*) as count, SUM(t.total) as total")
            ->when($tanggalAwal, fn($q) => $q->whereRaw('DATE(t.tanggal) >= ?', [$tanggalAwal]))
            ->when($tanggalAkhir, fn($q) => $q->whereRaw('DATE(t.tanggal) <= ?', [$tanggalAkhir]))
            ->groupBy('period')
            ->orderByDesc('period')
            ->get();

        if ($export === 'json') {
            return response()->json([
                'status' => true,
                'data'   => $transactions,
                'grand_total' => $grandTotal,
            ]);
        }

        return view('admin.laporan', compact('transactions', 'grandTotal', 'tanggalAwal', 'tanggalAkhir', 'periodTotals'));
    }
}
