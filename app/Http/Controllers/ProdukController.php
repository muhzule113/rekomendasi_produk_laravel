<?php

namespace App\Http\Controllers;

use App\Services\RecommenderService;
use Illuminate\Support\Facades\DB;

class ProdukController extends Controller
{
    public function index()
    {
        $categories = DB::table('categories')->orderBy('nama_category')->get();
        return view('customer.produk', compact('categories'));
    }

    public function detail($id)
    {
        $product = DB::table('products')
            ->join('categories', 'products.id_category', '=', 'categories.id_category')
            ->leftJoin(DB::raw("(SELECT id_product, ROUND(AVG(rating),1) as avg_rating, COUNT(*) as review_count FROM product_reviews GROUP BY id_product) r"),
                'products.id_product', '=', 'r.id_product')
            ->where('products.id_product', $id)
            ->select('products.*', 'categories.nama_category',
                DB::raw('COALESCE(r.avg_rating, 0) as avg_rating'),
                DB::raw('COALESCE(r.review_count, 0) as review_count'))
            ->first();

        if (!$product) abort(404);

        $recommender = app(RecommenderService::class);
        $similarProducts = $recommender->getProductSimilar($id, 4);
        $recommender->logRecommendationItems(auth()->id(), $similarProducts, 'CF_similarity', 'score');

        $sameCategory = DB::table('products')
            ->join('categories', 'products.id_category', '=', 'categories.id_category')
            ->where('products.id_category', $product->id_category)
            ->where('products.id_product', '!=', $id)
            ->where('products.status', 'aktif')
            ->select('products.*', 'categories.nama_category')
            ->orderByDesc('products.terjual')
            ->limit(4)
            ->get();

        $reviews = DB::table('product_reviews')
            ->join('users', 'product_reviews.id_user', '=', 'users.id_user')
            ->where('product_reviews.id_product', $id)
            ->select('product_reviews.*', 'users.nama')
            ->orderByDesc('product_reviews.created_at')
            ->get();

        $ratingStats = (object) [
            'review_count' => $product->review_count,
            'avg_rating'   => $product->avg_rating,
        ];

        $userHasReviewed = false;
        if (auth()->check()) {
            $userHasReviewed = DB::table('product_reviews')
                ->where('id_product', $id)
                ->where('id_user', auth()->id())
                ->exists();
        }

        return view('customer.detail-produk', compact('product', 'similarProducts', 'sameCategory', 'reviews', 'ratingStats', 'userHasReviewed'));
    }
}
