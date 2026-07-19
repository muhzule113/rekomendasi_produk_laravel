<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProdukController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search  = $request->query('search', '');
        $kategori = $request->query('kategori', '');
        $sort    = $request->query('sort', '');

        $query = DB::table('products as p')
            ->join('categories as c', 'p.id_category', '=', 'c.id_category')
            ->leftJoin(DB::raw("(SELECT id_product, ROUND(AVG(rating),1) as avg_rating, COUNT(*) as review_count FROM product_reviews GROUP BY id_product) r"),
                'p.id_product', '=', 'r.id_product')
            ->where('p.status', 'aktif')
            ->select(
                'p.*', 'c.nama_category',
                DB::raw('COALESCE(r.avg_rating, 0) as avg_rating'),
                DB::raw('COALESCE(r.review_count, 0) as review_count')
            );

        $countQuery = DB::table('products as p')
            ->join('categories as c', 'p.id_category', '=', 'c.id_category')
            ->where('p.status', 'aktif');

        if ($search) {
            $query->where('p.nama_product', 'like', "%{$search}%");
            $countQuery->where('p.nama_product', 'like', "%{$search}%");
        }

        if ($kategori) {
            $query->where('c.id_category', $kategori);
            $countQuery->where('c.id_category', $kategori);
        }

        match ($sort) {
            'terlaris' => $query->orderByDesc('p.terjual'),
            'termurah' => $query->orderBy('p.harga'),
            'termahal' => $query->orderByDesc('p.harga'),
            default    => $query->orderByDesc('p.id_product'),
        };

        $page  = max(1, (int) $request->query('page', 1));
        $limit = max(1, (int) $request->query('limit', 12));
        $offset = ($page - 1) * $limit;

        $totalRows = $countQuery->count();

        $products = $query->offset($offset)->limit($limit)->get()->map(fn($r) => (array) $r)->toArray();

        // Adjust stok based on cart session
        $cart = session('cart', []);
        foreach ($products as &$p) {
            $cartQty = $cart[$p['id_product']] ?? 0;
            $p['stok'] = max(0, (int) $p['stok'] - $cartQty);
        }

        return response()->json([
            'status'      => true,
            'data'        => $products,
            'total'       => $totalRows,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int) ceil($totalRows / $limit),
        ]);
    }
}
