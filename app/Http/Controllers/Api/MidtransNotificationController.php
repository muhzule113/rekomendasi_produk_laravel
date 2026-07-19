<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MidtransPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransNotificationController extends Controller
{
    public function handle(Request $request, MidtransPaymentService $midtrans): JsonResponse
    {
        try {
            MidtransPaymentService::configure();
            $notif = new \Midtrans\Notification();
        } catch (\Exception $e) {
            Log::error('Midtrans notification parse failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Invalid notification'], 400);
        }

        $transactionStatus = $notif->transaction_status;
        $paymentType = $notif->payment_type;
        $orderId = $notif->order_id;
        $fraudStatus = $notif->fraud_status ?? '';

        Log::info('Midtrans Notification', [
            'order_id' => $orderId,
            'transaction_status' => $transactionStatus,
            'payment_type' => $paymentType,
            'fraud_status' => $fraudStatus,
        ]);

        $trx = DB::table('transactions')->where('midtrans_order_id', $orderId)->first();
        if (!$trx) {
            Log::error("Midtrans Notification Error: Transaction not found for Order ID {$orderId}");
            return response()->json(['status' => 'ok'], 200);
        }

        $result = $midtrans->applyMidtransStatus(
            $trx,
            $transactionStatus,
            $fraudStatus,
            $paymentType
        );

        if (!$result['ok']) {
            Log::error('Midtrans notification update failed', $result);
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
