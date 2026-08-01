<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecommenderService
{
    public const METHOD_IBCF = 'Item-Based CF - Cosine Similarity';
    public const METHOD_COLD_START = 'Cold Start - Popularitas/Rating (bukan CF)';
    public const METHOD_BUY_AGAIN = 'Beli Lagi (Fallback, bukan CF)';
    public const METHOD_AVAILABLE = 'Produk Tersedia (Fallback, bukan CF)';

    public const LOG_IBCF = 'ibcf_cosine';
    public const LOG_COLD_START = 'cold_start_popular';
    public const LOG_BUY_AGAIN = 'buy_again';
    public const LOG_AVAILABLE = 'available_fallback';

    /**
     * Scope transaksi valid untuk CF: Selesai + Dibayar.
     */
    public static function applyValidTransactionFilter($query)
    {
        return $query
            ->where('transactions.status_pesanan', 'Selesai')
            ->where('transactions.status_pembayaran', 'Dibayar');
    }

    public function minCoOccurrence(): int
    {
        return max(1, (int) config('recommendation.min_co_occurrence', 2));
    }

    /**
     * Build User-Item binary matrix from completed paid transactions.
     */
    public function buildUserItemMatrix(): array
    {
        $query = DB::table('transactions')
            ->join('transaction_items', 'transactions.id_transaction', '=', 'transaction_items.id_transaction')
            ->select('transactions.id_user', 'transaction_items.id_product')
            ->groupBy('transactions.id_user', 'transaction_items.id_product');

        $results = self::applyValidTransactionFilter($query)->get();

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
            if (in_array($row->id_product, $products, true)) {
                $matrix[$row->id_user][$row->id_product] = 1;
            }
        }

        return $matrix;
    }

    /**
     * Binary cosine similarity tanpa normalisasi terhadap skor maksimum.
     * cosine(A,B) = |A∩B| / sqrt(|A| * |B|)
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
        sort($productIds, SORT_NUMERIC);
        $numProducts = count($productIds);

        if ($numProducts < 2) {
            return ['similarities' => [], 'stats' => ['error' => 'Jumlah produk aktif kurang dari 2.']];
        }

        $sizes = [];
        foreach ($productIds as $pid) {
            $sizes[$pid] = count($buyers[$pid]);
        }

        $minCo = $this->minCoOccurrence();
        $similarities = [];

        for ($i = 0; $i < $numProducts; $i++) {
            for ($j = $i + 1; $j < $numProducts; $j++) {
                $prodA = $productIds[$i];
                $prodB = $productIds[$j];

                $intersection = count(array_intersect_key($buyers[$prodA], $buyers[$prodB]));
                if ($intersection < $minCo) {
                    continue;
                }

                $denom = sqrt($sizes[$prodA] * $sizes[$prodB]);
                if ($denom <= 0) {
                    continue;
                }

                $cosine = $intersection / $denom;
                if ($cosine <= 0) {
                    continue;
                }

                $similarities[] = [
                    'product_a' => $prodA,
                    'product_b' => $prodB,
                    'score' => $cosine,
                    'co_occurrence' => $intersection,
                ];
            }
        }

        $savedPairsCount = count($similarities);
        $totalScore = 0.0;
        $maxScore = 0.0;
        $minScore = 1.0;
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
                'max_score' => $savedPairsCount > 0 ? round($maxScore, 6) : 0,
                'min_score' => $savedPairsCount > 0 ? round($minScore, 6) : 0,
                'avg_score' => $savedPairsCount > 0 ? round($totalScore / $savedPairsCount, 6) : 0,
                'min_co_occurrence' => $minCo,
            ],
        ];
    }

    /**
     * Hitung cosine dari dua vektor biner (untuk unit test / parity).
     *
     * @param  array<int, int|bool>  $vectorA
     * @param  array<int, int|bool>  $vectorB
     */
    public function cosineFromBinaryVectors(array $vectorA, array $vectorB): float
    {
        $len = max(count($vectorA), count($vectorB));
        $dot = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < $len; $i++) {
            $a = (int) ($vectorA[$i] ?? 0);
            $b = (int) ($vectorB[$i] ?? 0);
            $dot += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA === 0 || $normB === 0) {
            return 0.0;
        }

        return $dot / sqrt($normA * $normB);
    }

    /**
     * Save bidirectional similarity pairs atomically.
     * Empty input does not wipe existing data (caller must treat as failed calc).
     */
    public function saveSimilarity(array $similarities): bool
    {
        if (empty($similarities)) {
            return false;
        }

        try {
            DB::beginTransaction();
            DB::table('product_similarity')->delete();

            $inserts = [];
            foreach ($similarities as $sim) {
                $row = [
                    'product_a' => $sim['product_a'],
                    'product_b' => $sim['product_b'],
                    'score' => $sim['score'],
                    'co_occurrence' => $sim['co_occurrence'],
                    'source' => 'cf_purchase',
                    'updated_at' => now(),
                ];
                $inserts[] = $row;
                $inserts[] = [
                    'product_a' => $sim['product_b'],
                    'product_b' => $sim['product_a'],
                    'score' => $sim['score'],
                    'co_occurrence' => $sim['co_occurrence'],
                    'source' => 'cf_purchase',
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($inserts, 500) as $chunk) {
                DB::table('product_similarity')->insert($chunk);
            }

            $this->clearRecommendationDirty();
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Similarity Save Error: ' . $e->getMessage());
            return false;
        }
    }

    public function isRecommendationDirty(): bool
    {
        $value = DB::table('system_settings')
            ->where('setting_key', 'recommendation_dirty')
            ->value('setting_value');

        return (string) $value === '1';
    }

    public function setRecommendationDirty(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'recommendation_dirty'],
            ['setting_value' => '1', 'updated_at' => now()]
        );
    }

    public function clearRecommendationDirty(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'recommendation_dirty'],
            ['setting_value' => '0', 'updated_at' => now()]
        );
    }

    /**
     * Rating map for display only (not used in IBCF scoring).
     */
    private function getRatingMap(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        return DB::table('product_reviews')
            ->whereIn('id_product', $productIds)
            ->selectRaw('id_product, COALESCE(ROUND(AVG(rating),1),0) as avg_rating, COUNT(*) as review_count')
            ->groupBy('id_product')
            ->get()
            ->mapWithKeys(fn ($r) => [
                $r->id_product => ['avg' => (float) $r->avg_rating, 'count' => (int) $r->review_count],
            ])->toArray();
    }

    /**
     * Pure Item-Based CF recommendation with candidate aggregation.
     *
     * prediction_score(user, candidate)
     *   = sum(similarity(purchased_item, candidate)) / |purchased_items|
     */
    public function recommendForCustomer(int $customerId, int $limit = 8): array
    {
        $boughtProducts = $this->getBoughtProducts($customerId);
        if (empty($boughtProducts)) {
            return [];
        }

        $purchasedCount = count($boughtProducts);

        $rows = DB::table('product_similarity')
            ->join('products', 'product_similarity.product_b', '=', 'products.id_product')
            ->join('categories', 'products.id_category', '=', 'categories.id_category')
            ->whereIn('product_similarity.product_a', $boughtProducts)
            ->whereNotIn('product_similarity.product_b', $boughtProducts)
            ->where('products.status', 'aktif')
            ->where('products.stok', '>', 0)
            ->select(
                'products.*',
                'categories.nama_category',
                'product_similarity.score',
                'product_similarity.co_occurrence',
                'product_similarity.product_a as bought_id'
            )
            ->get();

        $aggregated = [];
        foreach ($rows as $row) {
            $candidateId = (int) $row->id_product;
            $sim = (float) $row->score;
            $co = (int) $row->co_occurrence;
            $boughtId = (int) $row->bought_id;

            if (!isset($aggregated[$candidateId])) {
                $aggregated[$candidateId] = [
                    'product' => (array) $row,
                    'sim_sum' => 0.0,
                    'max_sim' => -1.0,
                    'best_bought_id' => $boughtId,
                    'max_co' => $co,
                ];
            }

            $aggregated[$candidateId]['sim_sum'] += $sim;
            $aggregated[$candidateId]['max_co'] = max($aggregated[$candidateId]['max_co'], $co);

            if ($sim > $aggregated[$candidateId]['max_sim']) {
                $aggregated[$candidateId]['max_sim'] = $sim;
                $aggregated[$candidateId]['best_bought_id'] = $boughtId;
            }
        }

        if (empty($aggregated)) {
            return [];
        }

        $allBoughtIds = array_unique(array_map(fn ($a) => $a['best_bought_id'], $aggregated));
        $boughtNames = DB::table('products')
            ->whereIn('id_product', $allBoughtIds)
            ->pluck('nama_product', 'id_product')
            ->toArray();

        $results = [];
        foreach ($aggregated as $candidateId => $agg) {
            $row = $agg['product'];
            $prediction = $agg['sim_sum'] / $purchasedCount;
            $boughtName = $boughtNames[$agg['best_bought_id']] ?? 'produk lain';

            $row['score'] = round($prediction, 6);
            $row['prediction_score'] = round($prediction, 6);
            $row['co_occurrence'] = $agg['max_co'];
            $row['bought_id'] = $agg['best_bought_id'];
            $row['alasan'] = 'Direkomendasikan berdasarkan kemiripan pola pembelian dengan ' . $boughtName;
            $results[] = $row;
        }

        usort($results, function ($a, $b) {
            $cmp = $b['prediction_score'] <=> $a['prediction_score'];
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = ($b['co_occurrence'] ?? 0) <=> ($a['co_occurrence'] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }
            return ($a['id_product'] <=> $b['id_product']);
        });

        $results = array_slice($results, 0, $limit);

        $prodIds = array_column($results, 'id_product');
        $ratingMap = $this->getRatingMap($prodIds);
        foreach ($results as &$r) {
            $pid = $r['id_product'];
            $r['rating_avg'] = $ratingMap[$pid]['avg'] ?? 0;
            $r['rating_count'] = $ratingMap[$pid]['count'] ?? 0;
        }
        unset($r);

        return $results;
    }

    /**
     * Similar products for detail page (pure IBCF similarity, no rating in score).
     */
    public function recommendSimilarProduct(int $productId, int $limit = 4): array
    {
        $results = DB::table('product_similarity')
            ->join('products', 'product_similarity.product_b', '=', 'products.id_product')
            ->join('categories', 'products.id_category', '=', 'categories.id_category')
            ->where('product_similarity.product_a', $productId)
            ->where('product_similarity.product_b', '!=', $productId)
            ->where('products.status', 'aktif')
            ->where('products.stok', '>', 0)
            ->select('products.*', 'categories.nama_category', 'product_similarity.score', 'product_similarity.co_occurrence')
            ->orderByDesc('product_similarity.score')
            ->orderByDesc('product_similarity.co_occurrence')
            ->orderBy('products.id_product')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        if (!empty($results)) {
            $prodIds = array_column($results, 'id_product');
            $ratingMap = $this->getRatingMap($prodIds);
            foreach ($results as &$row) {
                $pid = $row['id_product'];
                $row['rating_avg'] = $ratingMap[$pid]['avg'] ?? 0;
                $row['rating_count'] = $ratingMap[$pid]['count'] ?? 0;
                $row['alasan'] = 'Mirip berdasarkan pola pembelian pelanggan';
            }
            unset($row);
        }

        return $results;
    }

    /**
     * Product IDs bought by customer from valid CF transactions only.
     */
    public function getBoughtProducts(int $customerId): array
    {
        $query = DB::table('transactions')
            ->join('transaction_items', 'transactions.id_transaction', '=', 'transaction_items.id_transaction')
            ->where('transactions.id_user', $customerId);

        return self::applyValidTransactionFilter($query)
            ->distinct()
            ->orderBy('transaction_items.id_product')
            ->pluck('transaction_items.id_product')
            ->toArray();
    }

    /**
     * Fallback: Buy Again products (valid transactions only).
     */
    public function getBuyAgainProducts(int $customerId, int $limit = 8): array
    {
        $query = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.id_transaction', '=', 'transactions.id_transaction')
            ->join('products', 'transaction_items.id_product', '=', 'products.id_product')
            ->join('categories', 'products.id_category', '=', 'categories.id_category')
            ->where('transactions.id_user', $customerId)
            ->where('products.status', 'aktif')
            ->where('products.stok', '>', 0);

        $results = self::applyValidTransactionFilter($query)
            ->select(
                'products.*',
                'categories.nama_category',
                DB::raw('COUNT(transaction_items.id_product) as frekuensi_beli'),
                DB::raw('MAX(transactions.tanggal) as terakhir_dibeli')
            )
            ->groupBy('transaction_items.id_product')
            ->orderByDesc('frekuensi_beli')
            ->orderByDesc('terakhir_dibeli')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        foreach ($results as &$row) {
            $row['alasan'] = 'Anda pernah membeli produk ini ' . $row['frekuensi_beli'] . 'x';
        }

        return $results;
    }

    /**
     * Fallback cold start: popularity + rating (explicitly not CF).
     */
    public function getBestSellerProducts(int $limit = 8): array
    {
        $maxTerjual = DB::table('products')->max('terjual') ?: 1;

        $results = DB::table('products')
            ->join('categories', 'products.id_category', '=', 'categories.id_category')
            ->leftJoin(DB::raw('(SELECT id_product, ROUND(AVG(rating),1) as avg_rating, COUNT(*) as review_count FROM product_reviews GROUP BY id_product) r'),
                'products.id_product', '=', 'r.id_product')
            ->where('products.status', 'aktif')
            ->where('products.stok', '>', 0)
            ->select(
                'products.*',
                'categories.nama_category',
                DB::raw('COALESCE(r.avg_rating, 0) as avg_rating'),
                DB::raw('COALESCE(r.review_count, 0) as review_count')
            )
            ->orderByRaw('(COALESCE(r.avg_rating,0)/5 * 0.6 + (products.terjual / ' . (float) $maxTerjual . ') * 0.4) DESC')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        foreach ($results as &$row) {
            $alasan = [];
            if ($row['review_count'] >= 2 && $row['avg_rating'] >= 4.0) {
                $alasan[] = 'Rating ' . $row['avg_rating'] . ' (' . $row['review_count'] . ' ulasan)';
            }
            if ($row['terjual'] > 0) {
                $alasan[] = 'Terjual ' . $row['terjual'] . 'x';
            }
            $row['alasan'] = !empty($alasan) ? implode(' — ', $alasan) : 'Produk populer (fallback, bukan CF)';
        }

        return $results;
    }

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
            ->map(fn ($r) => (array) $r)
            ->toArray();

        foreach ($results as &$row) {
            $row['alasan'] = 'Produk tersedia untuk Anda (fallback, bukan CF)';
        }

        return $results;
    }

    /**
     * Master orchestrator with cascading fallbacks.
     *
     * @return array{method: string, message: ?string, data: array, log_source: string}
     */
    public function getFullRecommendation(?int $customerId, int $limit = 8): array
    {
        $boughtProducts = $customerId ? $this->getBoughtProducts($customerId) : [];

        if (empty($boughtProducts)) {
            $rated = $this->getBestSellerProducts($limit);
            if (!empty($rated)) {
                return [
                    'method' => self::METHOD_COLD_START,
                    'message' => 'Anda belum memiliki riwayat transaksi valid. Berikut produk populer berdasarkan rating/penjualan (bukan hasil Item-Based CF).',
                    'data' => $rated,
                    'log_source' => self::LOG_COLD_START,
                ];
            }

            return [
                'method' => self::METHOD_AVAILABLE,
                'message' => 'Anda belum memiliki riwayat transaksi. Berikut produk yang tersedia saat ini (fallback, bukan CF).',
                'data' => $this->getAvailableProducts($limit),
                'log_source' => self::LOG_AVAILABLE,
            ];
        }

        $cfResults = $this->recommendForCustomer($customerId, $limit);
        if (!empty($cfResults)) {
            return [
                'method' => self::METHOD_IBCF,
                'message' => null,
                'data' => $cfResults,
                'log_source' => self::LOG_IBCF,
            ];
        }

        $buyAgain = $this->getBuyAgainProducts($customerId, $limit);
        if (!empty($buyAgain)) {
            return [
                'method' => self::METHOD_BUY_AGAIN,
                'message' => 'Belum ada rekomendasi produk baru dari Item-Based CF. Berikut produk yang dapat Anda beli kembali (fallback).',
                'data' => $buyAgain,
                'log_source' => self::LOG_BUY_AGAIN,
            ];
        }

        $bestSeller = $this->getBestSellerProducts($limit);
        if (!empty($bestSeller)) {
            return [
                'method' => self::METHOD_COLD_START,
                'message' => 'Belum ada rekomendasi CF. Berikut produk terlaris dengan rating (fallback, bukan CF).',
                'data' => $bestSeller,
                'log_source' => self::LOG_COLD_START,
            ];
        }

        return [
            'method' => self::METHOD_AVAILABLE,
            'message' => 'Saat ini tidak ada rekomendasi khusus. Berikut produk yang tersedia (fallback, bukan CF).',
            'data' => $this->getAvailableProducts($limit),
            'log_source' => self::LOG_AVAILABLE,
        ];
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
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }
}
