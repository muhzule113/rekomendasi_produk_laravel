<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function store(Request $request, UploadService $uploadService): JsonResponse
    {
        if (!$request->hasFile('file_transaksi')) {
            return response()->json(['ok' => false, 'pesan' => 'Tidak ada file yang diupload']);
        }

        $file = $request->file('file_transaksi');

        // Convert UploadedFile to $_FILES-style array for UploadService compatibility
        $fileArray = [
            'name'     => $file->getClientOriginalName(),
            'tmp_name' => $file->getPathname(),
            'size'     => $file->getSize(),
            'error'    => $file->getError(),
        ];

        $result = $uploadService->handleUpload($fileArray, auth()->id());
        return response()->json($result);
    }
}
