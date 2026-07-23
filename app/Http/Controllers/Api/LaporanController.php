<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tanggal_awal = $request->query('tanggal_awal', '');
        $tanggal_akhir = $request->query('tanggal_akhir', '');
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(10, (int) $request->query('limit', 50)));
        $offset = ($page - 1) * $limit;

        $base = DB::table('transactions as t')
            ->join('users as u', 't.id_user', '=', 'u.id_user')
            ->when($tanggal_awal !== '', fn ($q) => $q->whereDate('t.tanggal', '>=', $tanggal_awal))
            ->when($tanggal_akhir !== '', fn ($q) => $q->whereDate('t.tanggal', '<=', $tanggal_akhir));

        $summary = (clone $base)
            ->selectRaw('COUNT(*) as total_rows, COALESCE(SUM(t.total), 0) as grand_total')
            ->first();

        $rows = (clone $base)
            ->select(
                't.id_transaction',
                't.tanggal',
                't.metode_pembayaran',
                't.status_pesanan',
                't.total',
                'u.nama'
            )
            ->orderByDesc('t.tanggal')
            ->orderByDesc('t.id_transaction')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $totalRows = (int) ($summary->total_rows ?? 0);
        $totalPages = max(1, (int) ceil($totalRows / $limit));
        $loaded = $offset + $rows->count();

        return response()->json([
            'status' => true,
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total_rows' => $totalRows,
                'total_pages' => $totalPages,
                'grand_total' => (float) ($summary->grand_total ?? 0),
                'has_more' => $loaded < $totalRows,
            ],
        ]);
    }
}
