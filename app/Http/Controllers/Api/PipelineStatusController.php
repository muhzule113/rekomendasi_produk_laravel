<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineStatusController extends Controller
{
    public function show(Request $request, UploadService $uploadService): JsonResponse
    {
        $id_upload = (int) $request->query('id', 0);
        $action    = $request->query('action', 'status');

        switch ($action) {
            case 'status':
                return response()->json($uploadService->getStatus($id_upload));

            case 'logs':
                $filter = $request->query('filter', 'all');
                return response()->json($uploadService->getLogBaris($id_upload, $filter));

            case 'riwayat':
                $sumber = $request->query('sumber', 'transaksi');
                return response()->json($uploadService->getRiwayat($sumber));

            default:
                return response()->json(['ok' => false, 'pesan' => 'Action tidak dikenal']);
        }
    }
}
