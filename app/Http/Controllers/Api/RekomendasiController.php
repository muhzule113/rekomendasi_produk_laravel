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
                $recommender->logRecommendationItems($id_user, $data, RecommenderService::LOG_IBCF, 'score');
                return response()->json(['status' => 'ok', 'data' => $data, 'method' => RecommenderService::METHOD_IBCF]);

            case 'personal':
                $user = auth()->user();
                if (!$user || $user->role !== 'pelanggan') {
                    $popular = $recommender->getPopularProducts($limit);
                    $recommender->logRecommendationItems(
                        $id_user,
                        $popular,
                        RecommenderService::LOG_COLD_START,
                        'avg_rating'
                    );
                    return response()->json([
                        'status' => 'ok',
                        'data' => $popular,
                        'method' => RecommenderService::METHOD_COLD_START,
                    ]);
                }
                $full = $recommender->getFullRecommendation($id_user, $limit);
                $recommender->logRecommendationItems(
                    $id_user,
                    $full['data'],
                    $full['log_source'],
                    'prediction_score',
                    'score'
                );
                return response()->json([
                    'status' => 'ok',
                    'data' => $full['data'],
                    'method' => $full['method'],
                ]);

            case 'popular':
                $data = $recommender->getPopularProducts($limit);
                $recommender->logRecommendationItems(
                    $id_user,
                    $data,
                    RecommenderService::LOG_COLD_START,
                    'avg_rating'
                );
                return response()->json([
                    'status' => 'ok',
                    'data' => $data,
                    'method' => RecommenderService::METHOD_COLD_START,
                ]);

            default:
                return response()->json(['status' => false, 'message' => 'Action tidak dikenal']);
        }
    }
}
