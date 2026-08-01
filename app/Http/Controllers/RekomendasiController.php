<?php

namespace App\Http\Controllers;

use App\Services\RecommenderService;

class RekomendasiController extends Controller
{
    public function index(RecommenderService $recommender)
    {
        $userId = auth()->id();
        $data = $recommender->getFullRecommendation($userId, 8);
        $popular = $recommender->getPopularProducts(4);

        $recommendations = $data['data'];
        $cart = session('cart', []);
        $stockMap = [];

        $recommendations = $this->applyCartStock($recommendations, $cart, $stockMap);
        $popular = $this->applyCartStock($popular, $cart, $stockMap);

        $logSource = $data['log_source'] ?? RecommenderService::LOG_IBCF;
        $recommender->logRecommendationItems(
            $userId,
            $recommendations,
            $logSource,
            'prediction_score',
            'score'
        );
        $recommender->logRecommendationItems(
            $userId,
            $popular,
            RecommenderService::LOG_COLD_START,
            'avg_rating'
        );

        return view('customer.rekomendasi', [
            'method' => $data['method'],
            'message' => $data['message'],
            'log_source' => $logSource,
            'recommendations' => $recommendations,
            'popular' => $popular,
            'stockMap' => $stockMap,
        ]);
    }

    /**
     * Reduce displayed stock by qty already in cart, and fill stockMap.
     */
    private function applyCartStock(array $products, array $cart, array &$stockMap): array
    {
        foreach ($products as &$p) {
            $pid = (int) $p['id_product'];
            $cartQty = (int) ($cart[$pid] ?? $cart[(string) $pid] ?? 0);
            $available = max(0, (int) $p['stok'] - $cartQty);
            $p['stok'] = $available;
            $stockMap[$pid] = $available;
        }
        unset($p);

        return $products;
    }
}
