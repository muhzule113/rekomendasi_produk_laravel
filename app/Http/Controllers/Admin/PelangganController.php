<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PelangganController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('users as u')
            ->leftJoin('transactions as t', 'u.id_user', '=', 't.id_user')
            ->select('u.*', DB::raw('COUNT(t.id_transaction) as total_transaksi'))
            ->where('u.role', 'pelanggan')
            ->groupBy('u.id_user')
            ->orderByDesc('u.id_user');

        if ($q = $request->get('q')) {
            $query->where(function ($qry) use ($q) {
                $qry->where('u.nama', 'like', "%{$q}%")
                    ->orWhere('u.email', 'like', "%{$q}%");
            });
        }
        if ($status = $request->get('status')) {
            $query->where('u.status', $status);
        }

        $customers   = $query->paginate(10)->appends($request->all());
        $currentPage = $customers->currentPage();
        $totalPages  = $customers->lastPage();

        return view('admin.pelanggan', compact('customers', 'currentPage', 'totalPages'));
    }

    public function transaksi($id)
    {
        $user = DB::table('users')->where('id_user', $id)->first();
        if (!$user) {
            return response()->json(['nama' => 'Tidak diketahui', 'data' => []], 404);
        }

        $transactions = DB::table('transactions')
            ->where('id_user', $id)
            ->orderByDesc('tanggal')
            ->get();

        return response()->json([
            'nama' => $user->nama,
            'data' => $transactions,
        ]);
    }
}
