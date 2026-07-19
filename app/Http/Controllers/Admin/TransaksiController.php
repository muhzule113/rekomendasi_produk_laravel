<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransaksiController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('transactions as t')
            ->join('users as u', 't.id_user', '=', 'u.id_user')
            ->select('t.*', 'u.nama');

        if ($q = $request->get('q')) {
            $query->where(function ($qry) use ($q) {
                $qry->where('t.id_transaction', 'like', "%{$q}%")
                    ->orWhere('u.nama', 'like', "%{$q}%");
            });
        }
        if ($sp = $request->get('status_pembayaran')) {
            $query->where('t.status_pembayaran', $sp);
        }
        if ($so = $request->get('status_pesanan')) {
            $query->where('t.status_pesanan', $so);
        }
        if ($tglAwal = $request->get('tanggal_awal')) {
            $query->whereRaw('DATE(t.tanggal) >= ?', [$tglAwal]);
        }
        if ($tglAkhir = $request->get('tanggal_akhir')) {
            $query->whereRaw('DATE(t.tanggal) <= ?', [$tglAkhir]);
        }

        $transactions = $query->orderByDesc('t.id_transaction')->paginate(10)->appends($request->all());
        $currentPage  = $transactions->currentPage();
        $totalPages   = $transactions->lastPage();

        return view('admin.transaksi', compact('transactions', 'currentPage', 'totalPages'));
    }

    public function updateStatus(Request $request, $id)
    {
        $action = $request->input('action');
        $newStatus = $request->input('new_status');
        $idAdmin = auth()->id();

        try {
            DB::beginTransaction();

            $current = DB::table('transactions')
                ->where('id_transaction', $id)
                ->lockForUpdate()
                ->first();

            if (!$current) {
                throw new \Exception("Transaksi tidak ditemukan.");
            }

            $fieldChanged = '';
            $oldValue = '';

            if ($action === 'update_pembayaran') {
                if ($current->metode_pembayaran === 'Midtrans') {
                    throw new \Exception("Status pembayaran Midtrans tidak boleh diubah manual dari sistem ini.");
                }
                if (!in_array($newStatus, ['Belum Dibayar', 'Dibayar'])) {
                    throw new \Exception("Nilai status pembayaran tidak valid.");
                }
                if ($current->status_pembayaran === 'Dibayar' && $newStatus === 'Belum Dibayar') {
                    throw new \Exception("Status yang sudah 'Dibayar' tidak boleh diubah mundur menjadi 'Belum Dibayar'.");
                }
                if ($current->status_pesanan === 'Selesai' && $newStatus !== $current->status_pembayaran) {
                    throw new \Exception("Tidak dapat mengubah pembayaran karena pesanan sudah bersifat final ('Selesai').");
                }

                $fieldChanged = 'status_pembayaran';
                $oldValue = $current->status_pembayaran;

                if ($oldValue !== $newStatus) {
                    DB::table('transactions')
                        ->where('id_transaction', $id)
                        ->update(['status_pembayaran' => $newStatus]);
                }
            } elseif ($action === 'update_pesanan') {
                if (!in_array($newStatus, ['Diproses', 'Selesai', 'Dibatalkan'])) {
                    throw new \Exception("Nilai status pesanan tidak valid.");
                }
                if ($newStatus === 'Selesai' && $current->status_pembayaran !== 'Dibayar') {
                    throw new \Exception("Pesanan tidak dapat diselesaikan ('Selesai') karena pembayaran masih 'Belum Dibayar'.");
                }
                if (in_array($current->status_pesanan, ['Selesai', 'Dibatalkan']) && $newStatus !== $current->status_pesanan) {
                    throw new \Exception("Status sudah final ('{$current->status_pesanan}') dan tidak dapat diubah kembali.");
                }

                $fieldChanged = 'status_pesanan';
                $oldValue = $current->status_pesanan;

                if ($oldValue !== $newStatus) {
                    DB::table('transactions')
                        ->where('id_transaction', $id)
                        ->update(['status_pesanan' => $newStatus]);
                }
            }

            if ($fieldChanged && $oldValue !== $newStatus) {
                // Audit log
                DB::table('transaction_status_logs')->insert([
                    'id_transaction' => $id,
                    'diubah_oleh'    => $idAdmin,
                    'field_changed'  => $fieldChanged,
                    'old_value'      => $oldValue,
                    'new_value'      => $newStatus,
                    'status_baru'    => $newStatus,
                ]);

                // Flag recommendation dirty
                if (
                    ($fieldChanged === 'status_pesanan' && $newStatus === 'Selesai' && $current->status_pembayaran === 'Dibayar') ||
                    ($fieldChanged === 'status_pembayaran' && $newStatus === 'Dibayar' && $current->status_pesanan === 'Selesai')
                ) {
                    DB::table('system_settings')
                        ->updateOrInsert(
                            ['setting_key' => 'recommendation_dirty'],
                            ['setting_value' => '1']
                        );
                }

                DB::commit();
                return redirect()->route('admin.transaksi')->with('success', "Status berhasil diubah dari '{$oldValue}' menjadi '{$newStatus}'.");
            }

            DB::rollBack();
            return redirect()->route('admin.transaksi');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('admin.transaksi')->with('error', $e->getMessage());
        }
    }

    public function detail($id)
    {
        $items = DB::table('transaction_items as ti')
            ->join('products as p', 'ti.id_product', '=', 'p.id_product')
            ->where('ti.id_transaction', $id)
            ->select('ti.*', 'p.nama_product')
            ->get();

        $trx = DB::table('transactions')
            ->where('id_transaction', $id)
            ->select('total', 'status_pembayaran', 'status_pesanan', 'metode_pembayaran')
            ->first();

        return response()->json([
            'items' => $items,
            'trx'   => $trx,
        ]);
    }
}
