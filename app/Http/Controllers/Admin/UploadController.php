<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UploadService;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route(
            $request->get('tab') === 'produk' ? 'admin.produk' : 'admin.transaksi'
        );
    }

    public function store(Request $request, UploadService $uploadService)
    {
        return $this->storeForSource($request, $uploadService, $request->get('tab', 'transaksi'));
    }

    public function storeTransaksi(Request $request, UploadService $uploadService)
    {
        return $this->storeForSource($request, $uploadService, 'transaksi');
    }

    public function storeProduk(Request $request, UploadService $uploadService)
    {
        return $this->storeForSource($request, $uploadService, 'produk');
    }

    private function storeForSource(Request $request, UploadService $uploadService, string $sumber)
    {
        $sumber = $sumber === 'produk' ? 'produk' : 'transaksi';

        $file = $request->file($sumber === 'produk' ? 'file_produk' : 'file_transaksi');

        if (!$file) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'pesan' => 'Tidak ada file yang diupload.']);
            }
            return back()->with('error', 'Tidak ada file yang diupload.');
        }

        $fileArray = [
            'name'     => $file->getClientOriginalName(),
            'tmp_name' => $file->getPathname(),
            'error'    => $file->getError(),
            'size'     => $file->getSize(),
        ];

        $result = $sumber === 'produk'
            ? $uploadService->handleProdukUpload($fileArray, auth()->id())
            : $uploadService->handleUpload($fileArray, auth()->id());

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        if ($result['ok']) {
            $historyRoute = $sumber === 'produk'
                ? 'admin.upload-history.produk'
                : 'admin.upload-history.transaksi';

            return redirect()->route($historyRoute, ['id' => $result['id_upload']])
                ->with('success', 'File berhasil diupload. Preprocessing sedang berjalan.');
        }

        return back()->with('error', $result['pesan'] ?? 'Upload gagal.');
    }
}
