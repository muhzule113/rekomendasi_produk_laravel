<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index()
    {
        $featured = DB::table('products')
            ->join('categories', 'products.id_category', '=', 'categories.id_category')
            ->leftJoin(DB::raw("(SELECT id_product, ROUND(AVG(rating),1) as avg_rating, COUNT(*) as review_count FROM product_reviews GROUP BY id_product) r"),
                'products.id_product', '=', 'r.id_product')
            ->where('products.status', 'aktif')
            ->select('products.*', 'categories.nama_category',
                DB::raw('COALESCE(r.avg_rating, 0) as avg_rating'),
                DB::raw('COALESCE(r.review_count, 0) as review_count'))
            ->orderByDesc('products.terjual')
            ->limit(4)
            ->get();

        $stockMap = [];
        $cart = session('cart', []);
        foreach ($featured as $p) {
            $cartQty = $cart[$p->id_product] ?? 0;
            $p->stok = max(0, (int)$p->stok - $cartQty);
            $stockMap[$p->id_product] = (int)$p->stok;
        }

        return view('customer.home', compact('featured', 'stockMap'));
    }
}
