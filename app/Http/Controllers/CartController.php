<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function index()
    {
        $cart = session('cart', []);
        if (empty($cart)) {
            return view('customer.keranjang', ['cartItems' => [], 'total' => 0]);
        }

        $productIds = array_keys($cart);
        $products = DB::table('products')
            ->join('categories', 'products.id_category', '=', 'categories.id_category')
            ->whereIn('products.id_product', $productIds)
            ->where('products.status', 'aktif')
            ->select('products.*', 'categories.nama_category')
            ->get()
            ->keyBy('id_product');

        $cartItems = [];
        $total = 0;
        foreach ($cart as $pid => $qty) {
            $qty = is_array($qty) ? ($qty['qty'] ?? 0) : (int)$qty;
            if ($qty <= 0 || !isset($products[$pid])) continue;
            $p = $products[$pid];
            $subtotal = $p->harga * $qty;
            $total += $subtotal;
            $cartItems[] = [
                'id_product' => $p->id_product,
                'nama_product' => $p->nama_product,
                'nama_category' => $p->nama_category,
                'foto' => $p->foto,
                'harga' => $p->harga,
                'stok' => $p->stok,
                'qty' => $qty,
                'subtotal' => $subtotal,
            ];
        }

        return view('customer.keranjang', compact('cartItems', 'total'));
    }
}
