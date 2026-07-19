<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'pelanggan') {
            return response()->json(['status' => false, 'message' => 'Unauthorized']);
        }

        $cart = session('cart', []);
        if (empty($cart)) {
            return response()->json(['status' => false, 'message' => 'Keranjang kosong']);
        }

        $alamat             = $request->input('alamat', '');
        $metode_pembayaran  = $request->input('metode_pembayaran', 'Tunai');

        DB::beginTransaction();
        try {
            $userId = $user->id_user;
            $total  = 0;
            $items  = [];

            foreach ($cart as $productId => $qty) {
                $product = DB::table('products')->where('id_product', $productId)->first();

                if (!$product || $product->stok < $qty) {
                    throw new \Exception("Stok produk tidak mencukupi.");
                }

                $subtotal = $qty * $product->harga;
                $total += $subtotal;
                $items[] = [
                    'id_product' => $productId,
                    'qty'        => $qty,
                    'harga'      => $product->harga,
                    'subtotal'   => $subtotal,
                ];
            }

            $transactionId = DB::table('transactions')->insertGetId([
                'id_user'           => $userId,
                'total'             => $total,
                'alamat_pengiriman' => $alamat,
                'metode_pembayaran' => $metode_pembayaran,
                'tanggal'           => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            foreach ($items as $item) {
                DB::table('transaction_items')->insert([
                    'id_transaction' => $transactionId,
                    'id_product'     => $item['id_product'],
                    'qty'            => $item['qty'],
                    'harga'          => $item['harga'],
                    'subtotal'       => $item['subtotal'],
                ]);

                DB::table('products')
                    ->where('id_product', $item['id_product'])
                    ->update([
                        'stok'    => DB::raw("stok - {$item['qty']}"),
                        'terjual' => DB::raw("terjual + {$item['qty']}"),
                    ]);
            }

            DB::commit();
            session(['cart' => []]);

            return response()->json([
                'status'         => true,
                'message'        => 'Checkout berhasil.',
                'transaction_id' => $transactionId,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }
}
