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
        $customer   = auth()->user();
        $isVerifiedCustomer = $customer?->isPelangganTerverifikasi() ?? false;
        $id_user    = $isVerifiedCustomer ? (int) $customer->id_user : null;
        $limit      = (int) $request->query('limit', 6);

        switch ($action) {
            case 'similar':
                $data = $recommender->getProductSimilar($id_product, $limit);
                if ($isVerifiedCustomer) {
                    $recommender->logRecommendationItems($id_user, $data, RecommenderService::LOG_IBCF, 'score');
                }
                return response()->json(['status' => 'ok', 'data' => $data, 'method' => RecommenderService::METHOD_IBCF]);

            case 'personal':
                if (!$isVerifiedCustomer) {
                    $popular = $recommender->getPopularProducts($limit);
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
                if ($isVerifiedCustomer) {
                    $recommender->logRecommendationItems(
                        $id_user,
                        $data,
                        RecommenderService::LOG_COLD_START,
                        'avg_rating'
                    );
                }
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
