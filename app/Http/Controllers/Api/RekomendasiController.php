<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecommenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RekomendasiController extends Controller
{
    public function index(Request $request, RecommenderService $recommender): JsonResponse
    {
        $action     = $request->query('action', 'similar');
        $id_product = (int) $request->query('id_product', 0);
        $id_user    = (int) (auth()->id() ?? 0);
        $limit      = (int) $request->query('limit', 6);

        switch ($action) {
            case 'similar':
                $data = $recommender->getProductSimilar($id_product, $limit);
                $recommender->logRecommendationItems($id_user, $data, 'CF_similarity', 'score');
                return response()->json(['status' => 'ok', 'data' => $data]);

            case 'personal':
                $user = auth()->user();
                if (!$user || $user->role !== 'pelanggan') {
                    return response()->json([
                        'status' => 'ok',
                        'data'   => $recommender->getPopularProducts($limit),
                    ]);
                }
                $data = $recommender->getPersonalRecommendations($id_user, $limit);
                $recommender->logRecommendationItems($id_user, $data, 'CF_personal', 'hybrid_score', 'score');
                return response()->json(['status' => 'ok', 'data' => $data]);

            case 'popular':
                $data = $recommender->getPopularProducts($limit);
                $recommender->logRecommendationItems($id_user, $data, 'popular', 'avg_rating');
                return response()->json(['status' => 'ok', 'data' => $data]);

            default:
                return response()->json(['status' => false, 'message' => 'Action tidak dikenal']);
        }
    }
}
