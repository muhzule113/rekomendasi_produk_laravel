<?php

namespace App\Http\Controllers;

use App\Services\MidtransPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiwayatController extends Controller
{
    public function index(Request $request, MidtransPaymentService $midtrans)
    {
        $userId = auth()->id();

        // Sync a specific order after Snap redirect (?order_id=TRX-...)
        $orderId = $request->query('order_id');
        if (!empty($orderId)) {
            $owned = DB::table('transactions')
                ->where('midtrans_order_id', $orderId)
                ->where('id_user', $userId)
                ->where('metode_pembayaran', 'Midtrans')
                ->where('status_pembayaran', 'Pending')
                ->exists();

            if ($owned) {
                $midtrans->syncByOrderId($orderId);
            }
        }

        // Also re-check other pending Midtrans orders for this user (webhook may have been missed)
        $pendingOrders = DB::table('transactions')
            ->where('id_user', $userId)
            ->where('metode_pembayaran', 'Midtrans')
            ->where('status_pembayaran', 'Pending')
            ->whereNotNull('midtrans_order_id')
            ->orderByDesc('tanggal')
            ->limit(5)
            ->pluck('midtrans_order_id');

        foreach ($pendingOrders as $pendingOrderId) {
            if ($pendingOrderId === $orderId) {
                continue; // already synced above
            }
            $midtrans->syncByOrderId($pendingOrderId);
        }

        $transactions = DB::table('transactions')
            ->where('id_user', $userId)
            ->orderByDesc('tanggal')
            ->get();

        foreach ($transactions as $trx) {
            $trx->items = DB::table('transaction_items')
                ->join('products', 'transaction_items.id_product', '=', 'products.id_product')
                ->join('categories', 'products.id_category', '=', 'categories.id_category')
                ->where('id_transaction', $trx->id_transaction)
                ->select('transaction_items.*', 'products.nama_product', 'categories.nama_category')
                ->get();
        }

        $clientKey = env('MIDTRANS_CLIENT_KEY', '');
        $snapJsUrl = MidtransPaymentService::snapJsUrl();

        return view('customer.riwayat', compact('transactions', 'clientKey', 'snapJsUrl'));
    }
}
