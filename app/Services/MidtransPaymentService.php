<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransPaymentService
{
    public static function isProduction(): bool
    {
        return filter_var(env('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function configure(): void
    {
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = self::isProduction();
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;
    }

    public static function apiBaseUrl(): string
    {
        return self::isProduction()
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }

    public static function snapJsUrl(): string
    {
        return self::isProduction()
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }

    /**
     * Fetch transaction status from Midtrans and sync local DB.
     *
     * @return array{ok: bool, message: string, payment_status?: string, already_paid?: bool}
     */
    public function syncByOrderId(string $orderId): array
    {
        $trx = DB::table('transactions')->where('midtrans_order_id', $orderId)->first();
        if (!$trx) {
            return ['ok' => false, 'message' => 'Transaksi tidak ditemukan'];
        }

        if ($trx->status_pembayaran === 'Dibayar') {
            return [
                'ok' => true,
                'message' => 'Pembayaran sudah dikonfirmasi',
                'payment_status' => 'Dibayar',
                'already_paid' => true,
            ];
        }

        $status = $this->fetchStatusFromMidtrans($orderId);
        if (!$status) {
            return ['ok' => false, 'message' => 'Gagal verifikasi ke Midtrans'];
        }

        return $this->applyMidtransStatus(
            $trx,
            $status['transaction_status'] ?? '',
            $status['fraud_status'] ?? '',
            $status['payment_type'] ?? ''
        );
    }

    public function fetchStatusFromMidtrans(string $orderId): ?array
    {
        $serverKey = env('MIDTRANS_SERVER_KEY');
        if (empty($serverKey)) {
            return null;
        }

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->acceptJson()
                ->timeout(15)
                ->get(self::apiBaseUrl() . "/v2/{$orderId}/status");

            if (!$response->successful()) {
                Log::warning('Midtrans status check failed', [
                    'order_id' => $orderId,
                    'http' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Midtrans status check error: ' . $e->getMessage(), [
                'order_id' => $orderId,
            ]);
            return null;
        }
    }

    /**
     * Apply Midtrans status fields to a local transaction row.
     *
     * @param  object  $trx  Row from transactions table
     * @return array{ok: bool, message: string, payment_status?: string}
     */
    public function applyMidtransStatus(
        object $trx,
        string $transactionStatus,
        string $fraudStatus = '',
        string $paymentType = ''
    ): array {
        [$newPembayaran, $newPesanan, $isSuccess] = $this->mapStatus(
            $transactionStatus,
            $fraudStatus,
            $paymentType,
            $trx->status_pembayaran
        );

        $idTrx = $trx->id_transaction;
        $oldPembayaran = $trx->status_pembayaran;

        DB::beginTransaction();
        try {
            $update = [
                'status_pembayaran' => $newPembayaran,
                'status_pesanan' => $newPesanan ?: $trx->status_pesanan,
                'payment_type' => $paymentType ?: $trx->payment_type,
                'fraud_status' => $fraudStatus ?: $trx->fraud_status,
                'payment_status' => $transactionStatus,
                'updated_at' => now(),
            ];

            if ($isSuccess) {
                $update['paid_at'] = now();
            }

            DB::table('transactions')
                ->where('id_transaction', $idTrx)
                ->update($update);

            if ($isSuccess && $oldPembayaran !== 'Dibayar') {
                $items = DB::table('transaction_items')->where('id_transaction', $idTrx)->get();
                foreach ($items as $item) {
                    DB::table('products')
                        ->where('id_product', $item->id_product)
                        ->update([
                            'stok' => DB::raw("stok - {$item->qty}"),
                            'terjual' => DB::raw("terjual + {$item->qty}"),
                        ]);
                }

                DB::table('system_settings')->updateOrInsert(
                    ['setting_key' => 'recommendation_dirty'],
                    ['setting_value' => '1']
                );
            }

            DB::commit();

            return [
                'ok' => true,
                'message' => 'Status diperbarui',
                'payment_status' => $newPembayaran,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Midtrans sync DB error: ' . $e->getMessage(), [
                'order_id' => $trx->midtrans_order_id ?? null,
            ]);

            return [
                'ok' => false,
                'message' => 'Gagal update: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{0: string, 1: string, 2: bool} [status_pembayaran, status_pesanan, is_success]
     */
    public function mapStatus(
        string $transactionStatus,
        string $fraudStatus,
        string $paymentType,
        string $currentPembayaran
    ): array {
        $newPembayaran = $currentPembayaran;
        $newPesanan = '';
        $isSuccess = false;

        if ($transactionStatus === 'capture') {
            // Credit card may be challenged by fraud check
            if ($paymentType === 'credit_card' && $fraudStatus === 'challenge') {
                $newPembayaran = 'Pending';
                $newPesanan = 'Menunggu Pembayaran';
            } else {
                $newPembayaran = 'Dibayar';
                $newPesanan = 'Diproses';
                $isSuccess = true;
            }
        } elseif ($transactionStatus === 'settlement') {
            $newPembayaran = 'Dibayar';
            $newPesanan = 'Diproses';
            $isSuccess = true;
        } elseif ($transactionStatus === 'pending') {
            $newPembayaran = 'Pending';
            $newPesanan = 'Menunggu Pembayaran';
        } elseif ($transactionStatus === 'deny') {
            $newPembayaran = 'Gagal';
            $newPesanan = 'Dibatalkan';
        } elseif ($transactionStatus === 'expire') {
            $newPembayaran = 'Expired';
            $newPesanan = 'Dibatalkan';
        } elseif ($transactionStatus === 'cancel') {
            $newPembayaran = 'Dibatalkan';
            $newPesanan = 'Dibatalkan';
        } elseif ($transactionStatus === 'refund') {
            $newPembayaran = 'Refund';
        }

        return [$newPembayaran, $newPesanan, $isSuccess];
    }
}
