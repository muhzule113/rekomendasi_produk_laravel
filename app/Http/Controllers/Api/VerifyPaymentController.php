<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MidtransPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyPaymentController extends Controller
{
    public function verify(Request $request, MidtransPaymentService $midtrans): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $orderId = $request->input('order_id', '');
        if ($orderId === '' && $request->route('id')) {
            // Admin pipeline route: /pipeline-data/verify-payment/{id}
            $trx = \Illuminate\Support\Facades\DB::table('transactions')
                ->where('id_transaction', $request->route('id'))
                ->first();
            $orderId = $trx->midtrans_order_id ?? '';
        }

        if (empty($orderId)) {
            return response()->json(['status' => false, 'message' => 'Order ID diperlukan'], 422);
        }

        $trx = \Illuminate\Support\Facades\DB::table('transactions')
            ->where('midtrans_order_id', $orderId)
            ->first();

        if (!$trx) {
            return response()->json(['status' => false, 'message' => 'Transaksi tidak ditemukan'], 404);
        }

        // Customer may only verify their own orders; admin can verify any
        $isAdmin = ($user->role ?? null) === 'admin';
        if (!$isAdmin && (int) $trx->id_user !== (int) $user->id_user) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 403);
        }

        $result = $midtrans->syncByOrderId($orderId);

        return response()->json([
            'status' => $result['ok'],
            'message' => $result['message'],
            'payment_status' => $result['payment_status'] ?? null,
        ], $result['ok'] ? 200 : 400);
    }
}
