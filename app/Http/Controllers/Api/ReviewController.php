<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $id_product = (int) $request->input('id_product', 0);
        $rating     = (int) $request->input('rating', 0);
        $komentar   = trim($request->input('komentar', ''));

        if ($id_product < 1) {
            return response()->json(['status' => false, 'message' => 'Produk tidak valid.']);
        }

        if ($rating < 1 || $rating > 5) {
            return response()->json(['status' => false, 'message' => 'Rating harus 1-5.']);
        }

        $user = auth()->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Silakan login terlebih dahulu.']);
        }

        // Check product exists
        $exists = DB::table('products')->where('id_product', $id_product)->exists();
        if (!$exists) {
            return response()->json(['status' => false, 'message' => 'Produk tidak ditemukan.']);
        }

        try {
            DB::table('product_reviews')->insert([
                'id_product' => $id_product,
                'id_user' => $user->id_user,
                'rating' => $rating,
                'komentar' => $komentar,
                'created_at' => now(),
            ]);

            // Recalculate avg rating
            $stats = DB::table('product_reviews')
                ->where('id_product', $id_product)
                ->selectRaw('ROUND(AVG(rating),1) as avg_rating, COUNT(*) as review_count')
                ->first();

            return response()->json([
                'status'       => true,
                'message'      => 'Ulasan berhasil disimpan!',
                'avg_rating'   => (float) $stats->avg_rating,
                'review_count' => (int) $stats->review_count,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Duplicate entry (23000) — user already reviewed this product
            if ($e->getCode() == 23000) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Anda sudah memberikan ulasan untuk produk ini.',
                ]);
            }
            return response()->json(['status' => false, 'message' => 'Gagal menyimpan ulasan.']);
        }
    }
}
