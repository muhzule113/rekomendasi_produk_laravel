<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function index()
    {
        $cart = session('cart', []);
        if (empty($cart)) return redirect()->route('keranjang');

        $productIds = array_keys($cart);
        $products = DB::table('products')
            ->whereIn('id_product', $productIds)
            ->where('status', 'aktif')
            ->get()
            ->keyBy('id_product');

        $cartItems = [];
        $subtotal = 0;
        foreach ($cart as $pid => $qty) {
            $qty = is_array($qty) ? ($qty['qty'] ?? 0) : (int)$qty;
            if ($qty <= 0 || !isset($products[$pid])) continue;
            $itemSubtotal = $products[$pid]->harga * $qty;
            $subtotal += $itemSubtotal;
            $cartItems[] = [
                'product' => $products[$pid],
                'qty' => $qty,
                'subtotal' => $itemSubtotal,
            ];
        }

        $midtransEnabled = !empty(env('MIDTRANS_SERVER_KEY'));
        $midtransClientKey = env('MIDTRANS_CLIENT_KEY', '');
        $user = auth()->user();

        return view('customer.checkout', compact('cartItems', 'subtotal', 'midtransEnabled', 'midtransClientKey', 'user'));
    }

    public function store()
    {
        $user = auth()->user();
        $cart = session('cart', []);
        if (empty($cart)) return response()->json(['status' => false, 'message' => 'Keranjang kosong']);

        $productIds = array_keys($cart);
        $products = DB::table('products')->whereIn('id_product', $productIds)->get()->keyBy('id_product');

        DB::beginTransaction();
        try {
            $total = 0;
            $items = [];

            foreach ($cart as $pid => $qty) {
                $qty = is_array($qty) ? ($qty['qty'] ?? 0) : (int)$qty;
                if ($qty <= 0) continue;
                $p = $products[$pid] ?? null;
                if (!$p) continue;
                if ($p->stok < $qty) throw new \Exception("Stok {$p->nama_product} tidak mencukupi.");

                $itemSubtotal = $p->harga * $qty;
                $total += $itemSubtotal;
                $items[] = ['product' => $p, 'qty' => $qty, 'subtotal' => $itemSubtotal];
            }

            if (empty($items)) throw new \Exception('Keranjang kosong atau produk tidak tersedia.');

            $request = request();
            $metode = $request->input('metode_pembayaran', 'Tunai');

            $transaction = Transaction::create([
                'id_user' => $user->id_user,
                'kode_transaksi' => 'TRX-' . date('Ymd') . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT),
                'total' => $total,
                'subtotal' => $total,
                'metode_pembayaran' => $metode,
                'status_pembayaran' => $metode === 'Midtrans' ? 'Pending' : 'Belum Dibayar',
                'status_pesanan' => 'Menunggu Pembayaran',
                'alamat_pengiriman' => $user->alamat,
                'nama_penerima' => $user->nama,
                'no_hp_penerima' => $user->no_hp,
                'sumber_data' => 'langsung',
            ]);

            foreach ($items as $item) {
                DB::table('transaction_items')->insert([
                    'id_transaction' => $transaction->id_transaction,
                    'id_product'     => $item['product']->id_product,
                    'nama_snapshot'  => $item['product']->nama_product,
                    'harga_snapshot' => $item['product']->harga,
                    'qty'            => $item['qty'],
                    'harga'          => $item['product']->harga,
                    'subtotal'       => $item['subtotal'],
                ]);

                if ($metode !== 'Midtrans') {
                    DB::table('products')->where('id_product', $item['product']->id_product)->update([
                        'stok'    => DB::raw("stok - {$item['qty']}"),
                        'terjual' => DB::raw("terjual + {$item['qty']}"),
                    ]);
                }
            }

            session()->forget('cart');
            DB::commit();

            if ($metode === 'Midtrans' && !empty(env('MIDTRANS_SERVER_KEY'))) {
                \App\Services\MidtransPaymentService::configure();

                $params = [
                    'transaction_details' => [
                        'order_id'     => $transaction->kode_transaksi,
                        'gross_amount' => $total,
                    ],
                    'customer_details' => [
                        'first_name' => $user->nama,
                        'email'      => $user->email,
                        'phone'      => $user->no_hp,
                    ],
                ];

                try {
                    $snapToken = \Midtrans\Snap::getSnapToken($params);
                    $transaction->update([
                        'snap_token'        => $snapToken,
                        'midtrans_order_id' => $transaction->kode_transaksi,
                    ]);
                    return view('customer.checkout-midtrans', compact('snapToken', 'transaction'));
                } catch (\Exception $e) {
                    // Fallback to biasa
                }
            }

            return redirect()->route('riwayat')->with('success', 'Pesanan berhasil dibuat!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('keranjang')->with('error', $e->getMessage());
        }
    }
}
