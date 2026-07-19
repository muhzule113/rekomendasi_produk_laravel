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
        $tanggal_awal  = $request->query('tanggal_awal', '');
        $tanggal_akhir = $request->query('tanggal_akhir', '');

        $query = DB::table('transactions as t')
            ->join('users as u', 't.id_user', '=', 'u.id_user')
            ->select('t.*', 'u.nama');

        if ($tanggal_awal) {
            $query->whereDate('t.tanggal', '>=', $tanggal_awal);
        }

        if ($tanggal_akhir) {
            $query->whereDate('t.tanggal', '<=', $tanggal_akhir);
        }

        $transactions = $query->orderByDesc('t.tanggal')->get()->toArray();

        return response()->json(['status' => true, 'data' => $transactions]);
    }
}
