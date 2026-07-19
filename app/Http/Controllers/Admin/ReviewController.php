<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $rating_filter = (int) $request->get('rating', 0);

        $query = DB::table('product_reviews as r')
            ->join('products as p', 'r.id_product', '=', 'p.id_product')
            ->join('users as u', 'r.id_user', '=', 'u.id_user')
            ->join('categories as c', 'p.id_category', '=', 'c.id_category')
            ->select('r.*', 'p.nama_product', 'u.nama as reviewer', 'c.nama_category');

        if ($search) {
            $query->where(function ($qry) use ($search) {
                $qry->where('p.nama_product', 'like', "%{$search}%")
                    ->orWhere('u.nama', 'like', "%{$search}%");
            });
        }
        if ($rating_filter) {
            $query->where('r.rating', $rating_filter);
        }

        $reviews = $query->orderByDesc('r.created_at')->paginate(12)->appends($request->all());

        // Statistik
        $total_reviews    = DB::table('product_reviews')->count();
        $avg_rating       = round(DB::table('product_reviews')->avg('rating') ?: 0, 1);
        $total_reviewers  = DB::table('product_reviews')->distinct()->count('id_user');
        $total_rated_prods = DB::table('product_reviews')->distinct()->count('id_product');

        // Distribusi rating
        $dist = DB::table('product_reviews')
            ->select('rating', DB::raw('COUNT(*) as cnt'))
            ->groupBy('rating')
            ->orderByDesc('rating')
            ->get();

        $distMap = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($dist as $d) {
            $distMap[(int)$d->rating] = (int)$d->cnt;
        }

        $chartLabels = json_encode(['5 Bintang', '4 Bintang', '3 Bintang', '2 Bintang', '1 Bintang']);
        $chartData   = json_encode([
            $distMap[5], $distMap[4], $distMap[3], $distMap[2], $distMap[1],
        ]);
        $chartColors = json_encode(['#f59e0b', '#fbbf24', '#fcd34d', '#94a3b8', '#cbd5e1']);

        // Top rated products
        $top_rated = DB::table('product_reviews as r')
            ->join('products as p', 'r.id_product', '=', 'p.id_product')
            ->join('categories as c', 'p.id_category', '=', 'c.id_category')
            ->select('p.nama_product', 'c.nama_category',
                     DB::raw('COUNT(r.id_review) as review_count'),
                     DB::raw('ROUND(AVG(r.rating), 1) as avg_rating'))
            ->groupBy('r.id_product', 'p.nama_product', 'c.nama_category')
            ->havingRaw('review_count >= 2')
            ->orderByDesc('avg_rating')
            ->orderByDesc('review_count')
            ->limit(10)
            ->get();

        return view('admin.reviews', [
            'reviews'           => $reviews,
            'total_reviews'     => $total_reviews,
            'avg_rating'        => $avg_rating,
            'total_reviewers'   => $total_reviewers,
            'total_rated_prods' => $total_rated_prods,
            'top_rated'         => $top_rated,
            'search'            => $search,
            'rating_filter'     => $rating_filter,
            'chartLabels'       => $chartLabels,
            'chartData'         => $chartData,
            'chartColors'       => $chartColors,
            'page'              => $reviews->currentPage(),
            'total_pages'       => $reviews->lastPage(),
        ]);
    }
}
