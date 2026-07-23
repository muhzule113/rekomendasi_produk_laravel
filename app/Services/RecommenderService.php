<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecommenderService
{
    /**
     * Build User-Item binary matrix from completed transactions
     */
    public function buildUserItemMatrix(): array
    {
        $results = DB::table('transactions')
            ->join('transaction_items', 'transactions.id_transaction', '=', 'transaction_items.id_transaction')
            ->where('transactions.status_pesanan', 'Selesai')
            ->where('transactions.status_pembayaran', 'Dibayar')
            ->select('transactions.id_user', 'transaction_items.id_product')
            ->groupBy('transactions.id_user', 'transaction_items.id_product')
            ->get();

        $products = DB::table('products')->where('status', 'aktif')->pluck('id_product')->toArray();

        if ($results->isEmpty() || empty($products)) {
            return [];
        }

        $users = $results->pluck('id_user')->unique()->toArray();

        $matrix = [];
        foreach ($users as $userId) {
            foreach ($products as $prodId) {
                $matrix[$userId][$prodId] = 0;
            }
        }

        foreach ($results as $row) {
            if (in_array($row->id_product, $products)) {
                $matrix[$row->id_user][$row->id_product] = 1;
            }
        }

        return $matrix;
    }

    /**
     * Dice similarity + normalisasi ke [0,1] relatif terhadap pasangan terkuat.
     * # ponytail: skor relatif ke max katalog; absolute Dice jika banding lintas dataset
     */
    public function calculateCosineSimilarity(array $matrix): array
    {
        if (empty($matrix)) {
            return ['similarities' => [], 'stats' => ['error' => 'Matrix kosong, tidak cukup data transaksi.']];
        }

        $buyers = [];
        foreach ($matrix as $userId => $products) {
            foreach ($products as $prodId => $value) {
                if ($value > 0) {
                    $buyers[$prodId][$userId] = true;
                }
            }
        }

        $productIds = array_keys($buyers);
        $numProducts = count($productIds);

        if ($numProducts < 2) {
            return ['similarities' => [], 'stats' => ['error' => 'Jumlah produk aktif kurang dari 2.']];
        }

        $sizes = [];
        foreach ($productIds as $pid) {
            $sizes[$pid] = count($buyers[$pid]);
        }

        $similarities = [];
        $maxRaw = 0;

        for ($i = 0; $i < $numProducts; $i++) {
            for ($j = $i + 1; $j < $numProducts; $j++) {
                $prodA = $productIds[$i];
                $prodB = $productIds[$j];

                $intersection = count(array_intersect_key($buyers[$prodA], $buyers[$prodB]));
                if ($intersection === 0) {
                    continue;
                }

                $dice = (2 * $intersection) / ($sizes[$prodA] + $sizes[$prodB]);
                $maxRaw = max($maxRaw, $dice);
                $similarities[] = [
                    'product_a' => $prodA,
                    'product_b' => $prodB,
                    'score' => $dice,
                    'co_occurrence' => $intersection,
                ];
            }
        }

        if ($maxRaw > 0) {
            foreach ($similarities as &$sim) {
                $sim['score'] = $sim['score'] / $maxRaw;
            }
            unset($sim);
        }

        $savedPairsCount = count($similarities);
        $totalScore = 0;
        $maxScore = 0;
        $minScore = 1;
        foreach ($similarities as $sim) {
            $totalScore += $sim['score'];
            $maxScore = max($maxScore, $sim['score']);
            $minScore = min($minScore, $sim['score']);
        }

        $expectedPairs = ($numProducts * ($numProducts - 1)) / 2;
        $coverage = $expectedPairs > 0 ? ($savedPairsCount / $expectedPairs) * 100 : 0;

        return [
            'similarities' => $similarities,
            'stats' => [
                'total_users' => count($matrix),
                'total_products_in_matrix' => $numProducts,
                'expected_pairs' => $expectedPairs,
                'saved_pairs' => $savedPairsCount,
                'coverage_percentage' => round($coverage, 2),
                'max_score' => round($maxScore, 4),
                'min_score' => $savedPairsCount > 0 ? round($minScore, 4) : 0,
                'avg_score' => $savedPairsCount > 0 ? round($totalScore / $savedPairsCount, 4) : 0,
            ],
        ];
    }

    /**
     * Save bidirectional similarity pairs to DB
     */
    public function saveSimilarity(array $similarities): bool
    {
        if (empty($similarities)) return true;

        try {
            DB::beginTransaction();
            DB::table('product_similarity')->delete();

            $inserts = [];
            foreach ($similarities as $sim) {
                $inserts[] = [
                    'product_a' => $sim['product_a'], 'product_b' => $sim['product_b'],
                    'score' => $sim['score'], 'co_occurrence' => $sim['co_occurrence'],
                    'updated_at' => now(),
                ];
                $inserts[] = [
                    'product_a' => $sim['product_b'], 'product_b' => $sim['product_a'],
                    'score' => $sim['score'], 'co_occurrence' => $sim['co_occurrence'],
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($inserts, 500) as $chunk) {
                DB::table('product_similarity')->insert($chunk);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Similarity Save Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get rating map for product IDs
     */
    private function getRatingMap(array $productIds): array
    {
        if (empty($productIds)) return [];

        return DB::table('product_reviews')
            ->whereIn('id_product', $productIds)
            ->selectRaw('id_product, COALESCE(ROUND(AVG(rating),1),0) as avg_rating, COUNT(*) as review_count')
            ->groupBy('id_product')
            ->get()
            ->mapWithKeys(fn($r) => [
                $r->id_product => ['avg' => (float)$r->avg_rating, 'count' => (int)$r->review_count]
            ])->toArray();
    }

    /**
     * Get low-rated product IDs (≤2.0 with ≥2 reviews)
     */
    private function getLowRatedProductIds(): array
    {
        return DB::table('product_reviews')
            ->select('id_product')
            ->selectRaw('COALESCE(ROUND(AVG(rating),1),0) as avg_rating, COUNT(*) as cnt')
            ->groupBy('id_product')
            ->havingRaw('avg_rating <= 2.0 AND cnt >= 2')
            ->pluck('id_product')->toArray();
    }

    /**
     * Apply hybrid scoring: 70% similarity + 30% rating + boost
     */
    private function applyHybridScoring(array &$results, array $ratingMap): void
    {
        foreach ($results as &$row) {
            $pid = $row['id_product'];
            $sim = $row['score'] ?? 0;
            $r = $ratingMap[$pid] ?? null;
            $avg = $r ? $r['avg'] : 0;
            $count = $r ? $r['count'] : 0;
            $normRating = $avg / 5.0;
            $hybrid = ($sim * 0.7) + ($normRating * 0.3);
            if ($avg >= 4.0 && $count >= 2) $hybrid += 0.1;

            $row['hybrid_score'] = round($hybrid, 4);
            $row['rating_avg'] = $avg;
            $row['rating_count'] = $count;
        }

        usort($results, fn($a, $b) => $b['hybrid_score'] <=> $a['hybrid_score']);
    }

    /**
     * Core Item-Based CF recommendation for a customer
     */
    public function recommendForCustomer(int $customerId, int $limit = 8): array
    {
        $boughtProducts = $this->getBoughtProducts($customerId);
        if (empty($boughtProducts)) return [];

        $lowRatedIds = $this->getLowRatedProductIds();
        $excludeIds = array_merge($boughtProducts, $lowRatedIds);

        $results = DB::table('product_similarity')
            ->join('products', 'product_similarity.product_b', '=', 'products.id_product')
            ->join('categories', 'products.id_category', '=', 'categories.id_category')
            ->whereIn('product_similarity.product_a', $boughtProducts)
            ->whereNotIn('product_similarity.product_b', $excludeIds)
            ->where('products.status', 'aktif')
            ->where('products.stok', '>', 0)
            ->select('products.*', 'categories.nama_category', 'product_similarity.score', 'product_similarity.product_a as bought_id')
            ->orderByDesc('product_similarity.score')
            ->limit($limit)
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();

        // Deduplicate
        $uniqueRecs = [];
        $prodIds = [];
        foreach ($results as $row) {
            $id = $row['id_product'];
            if (!isset($uniqueRecs[$id])) {
                $boughtName = DB::table('products')->where('id_product', $row['bought_id'])->value('nama_product');
                $row['alasan'] = "Sering dibeli bersama " . ($boughtName ?? 'produk lain');
                $uniqueRecs[$id] = $row;
                $prodIds[] = $id;
            }
        }
        $uniqueRecs = array_values($uniqueRecs);

        if (!empty($uniqueRecs)) {
            $ratingMap = $this->getRatingMap($prodIds);
            $this->applyHybridScoring($uniqueRecs, $ratingMap);
            foreach ($uniqueRecs as &$r) {
                if (!empty($r['rating_avg']) && $r['rating_count'] >= 2) {
                    $r['alasan'] = ($r['alasan'] ?? '') . " (rating " . $r['rating_avg'] . ")";
                }
            }
        }

        return array_slice($uniqueRecs, 0, $limit);
    }

    /**
     * Similar products for detail page
     */
    public function recommendSimilarProduct(int $productId, int $limit = 4): array
    {
        $lowRatedIds = $this->getLowRatedProductIds();
        $excludeIds = array_merge([$productId], $lowRatedIds);

        $results = DB::table('product_similarity')
            ->join('products', 'product_similarity.product_b', '=', 'products.id_product')
            ->join('categories', 'products.id_category', '=', 'categories.id_category')
            ->where('product_similarity.product_a', $productId)
            ->whereNotIn('product_similarity.product_b', $excludeIds)
            ->where('products.status', 'aktif')
            ->where('products.stok', '>', 0)
            ->select('products.*', 'categories.nama_category', 'product_similarity.score', 'product_similarity.co_occurrence')
            ->orderByDesc('product_similarity.score')
            ->orderByDesc('product_similarity.co_occurrence')
            ->limit($limit)
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();

        if (!empty($results)) {
            $prodIds = array_column($results, 'id_product');
            $ratingMap = $this->getRatingMap($prodIds);
            $this->applyHybridScoring($results, $ratingMap);
        }

        return $results;
    }

    /**
     * Get product IDs bought by a customer
     */
    public function getBoughtProducts(int $customerId): array
    {
        return DB::table('transactions')
            ->join('transaction_items', 'transactions.id_transaction', '=', 'transaction_items.id_transaction')
            ->where('transactions.id_user', $customerId)
            ->distinct()
            ->pluck('transaction_items.id_product')
            ->toArray();
    }

    /**
     * Fallback: Buy Again products
     */
    public function getBuyAgainProducts(int $customerId, int $limit = 8): array
    {
        $results = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.id_transaction', '=', 'transactions.id_transaction')
            ->join('products', 'transaction_items.id_product', '=', 'products.id_product')
            ->join('categories', 'products.id_category', '=', 'categories.id_category')
            ->where('transactions.id_user', $customerId)
            ->where('products.status', 'aktif')
            ->where('products.stok', '>', 0)
            ->select('products.*', 'categories.nama_category',
                DB::raw('COUNT(transaction_items.id_product) as frekuensi_beli'),
                DB::raw('MAX(transactions.tanggal) as terakhir_dibeli'))
            ->groupBy('transaction_items.id_product')
            ->orderByDesc('frekuensi_beli')
            ->orderByDesc('terakhir_dibeli')
            ->limit($limit)
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();

        foreach ($results as &$row) {
            $row['alasan'] = "Anda pernah membeli produk ini " . $row['frekuensi_beli'] . "x";
        }
        return $results;
    }

    /**
     * Fallback: Best Seller + Rating products
     */
    public function getBestSellerProducts(int $limit = 8): array
    {
        $maxTerjual = DB::table('products')->max('terjual') ?: 1;

        $results = DB::table('products')
            ->join('categories', 'products.id_category', '=', 'categories.id_category')
            ->leftJoin(DB::raw("(SELECT id_product, ROUND(AVG(rating),1) as avg_rating, COUNT(*) as review_count FROM product_reviews GROUP BY id_product) r"),
                'products.id_product', '=', 'r.id_product')
            ->where('products.status', 'aktif')
            ->where('products.stok', '>', 0)
            ->select('products.*', 'categories.nama_category',
                DB::raw('COALESCE(r.avg_rating, 0) as avg_rating'),
                DB::raw('COALESCE(r.review_count, 0) as review_count'))
            ->orderByRaw("(COALESCE(r.avg_rating,0)/5 * 0.6 + (products.terjual / {$maxTerjual}) * 0.4) DESC")
            ->limit($limit)
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();

        foreach ($results as &$row) {
            $alasan = [];
            if ($row['review_count'] >= 2 && $row['avg_rating'] >= 4.0) {
                $alasan[] = "Rating " . $row['avg_rating'] . " (" . $row['review_count'] . " ulasan)";
            }
            if ($row['terjual'] > 0) {
                $alasan[] = "Terjual " . $row['terjual'] . "x";
            }
            $row['alasan'] = !empty($alasan) ? implode(' — ', $alasan) : "Produk tersedia";
        }
        return $results;
    }

    /**
     * Fallback: Available products
     */
    public function getAvailableProducts(int $limit = 8): array
    {
        $results = DB::table('products')
            ->join('categories', 'products.id_category', '=', 'categories.id_category')
            ->where('products.status', 'aktif')
            ->where('products.stok', '>', 0)
            ->select('products.*', 'categories.nama_category')
            ->orderByDesc('products.id_product')
            ->limit($limit)
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();

        foreach ($results as &$row) {
            $row['alasan'] = "Produk tersedia untuk Anda";
        }
        return $results;
    }

    /**
     * Master orchestrator with cascading fallbacks
     */
    public function getFullRecommendation(?int $customerId, int $limit = 8): array
    {
        $boughtProducts = $customerId ? $this->getBoughtProducts($customerId) : [];

        if (empty($boughtProducts)) {
            $rated = $this->getBestSellerProducts($limit);
            if (!empty($rated)) {
                return [
                    'method' => 'Rating Tertinggi (Cold Start)',
                    'message' => 'Anda belum memiliki riwayat transaksi. Berikut produk dengan rating tertinggi dari pelanggan lain.',
                    'data' => $rated,
                ];
            }
            return [
                'method' => 'Produk Tersedia (Fallback)',
                'message' => 'Anda belum memiliki riwayat transaksi. Berikut produk yang tersedia saat ini.',
                'data' => $this->getAvailableProducts($limit),
            ];
        }

        $cfResults = $this->recommendForCustomer($customerId, $limit);

        if (!empty($cfResults)) {
            return ['method' => 'Item-Based CF + Hybrid Rating', 'message' => null, 'data' => $cfResults];
        }

        $buyAgain = $this->getBuyAgainProducts($customerId, $limit);
        if (!empty($buyAgain)) {
            return ['method' => 'Beli Lagi (Fallback)', 'message' => 'Belum ada rekomendasi produk baru. Berikut produk yang dapat Anda beli kembali.', 'data' => $buyAgain];
        }

        $bestSeller = $this->getBestSellerProducts($limit);
        if (!empty($bestSeller)) {
            return ['method' => 'Best Seller + Rating (Fallback)', 'message' => 'Belum ada rekomendasi produk baru. Berikut produk terlaris dengan rating tertinggi.', 'data' => $bestSeller];
        }

        return ['method' => 'Produk Tersedia (Fallback)', 'message' => 'Saat ini tidak ada rekomendasi khusus. Berikut produk yang tersedia.', 'data' => $this->getAvailableProducts($limit)];
    }

    public function getPersonalRecommendations(?int $id_user, int $limit = 6): array
    {
        return $this->getFullRecommendation($id_user, $limit)['data'];
    }

    public function getPopularProducts(int $limit = 6): array
    {
        return $this->getBestSellerProducts($limit);
    }

    public function getProductSimilar(int $id_product, int $limit = 6): array
    {
        return $this->recommendSimilarProduct($id_product, $limit);
    }

    public function logRecommendation(int $id_user, int $id_product, string $source, float $score): void
    {
        DB::table('recommendation_logs')->insert([
            'id_user' => $id_user,
            'id_product' => $id_product,
            'alasan' => $source,
            'score' => $score,
            'created_at' => now(),
        ]);
    }

    public function logRecommendationItems(?int $id_user, array $items, string $source, string ...$scoreKeys): void
    {
        if (empty($items)) {
            return;
        }

        try {
            $id_user = $this->resolveLogUserId($id_user);

            foreach ($items as $item) {
                $score = 0.0;
                foreach ($scoreKeys as $key) {
                    if (isset($item[$key])) {
                        $score = (float) $item[$key];
                        break;
                    }
                }
                $this->logRecommendation($id_user, (int) $item['id_product'], $source, $score);
            }
        } catch (\Throwable $e) {
            Log::warning('Recommendation log failed: ' . $e->getMessage());
        }
    }

    public function resolveLogUserId(?int $id_user = null): int
    {
        if ($id_user) {
            return $id_user;
        }

        return $this->guestUserId();
    }

    private function guestUserId(): int
    {
        static $guestId = null;
        if ($guestId !== null) {
            return $guestId;
        }

        $email = config('app.recommendation_guest_email', 'guest@system.local');
        $guestId = (int) DB::table('users')->where('email', $email)->value('id_user');

        if (!$guestId) {
            $guestId = (int) DB::table('users')->insertGetId([
                'nama' => 'Guest',
                'email' => $email,
                'password' => bcrypt(bin2hex(random_bytes(16))),
                'role' => 'pelanggan',
                'status' => 'aktif',
                'created_at' => now(),
            ]);
        }

        return $guestId;
    }

    /**
     * Top product pairs for admin
     */
    public function getTopRecommendedProducts(): array
    {
        return DB::table('product_similarity')
            ->join('products as p1', 'product_similarity.product_a', '=', 'p1.id_product')
            ->join('products as p2', 'product_similarity.product_b', '=', 'p2.id_product')
            ->whereColumn('product_similarity.product_a', '<', 'product_similarity.product_b')
            ->select('p1.nama_product as product_a_name', 'p2.nama_product as product_b_name', 'product_similarity.score', 'product_similarity.co_occurrence')
            ->orderByDesc('product_similarity.score')
            ->orderByDesc('product_similarity.co_occurrence')
            ->limit(10)
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();
    }
}
