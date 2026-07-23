<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UploadController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'transaksi');

        $riwayat = DB::table('data_uploads as d')
            ->join('users as u', 'd.id_user', '=', 'u.id_user')
            ->select('d.*', 'u.nama as nama_admin')
            ->orderByDesc('d.uploaded_at')
            ->limit(10)
            ->get();

        return view('admin.upload', compact('tab', 'riwayat'));
    }

    public function store(Request $request, UploadService $uploadService)
    {
        $tab = $request->get('tab', 'transaksi');

        $file = $tab === 'transaksi'
            ? $request->file('file_transaksi')
            : $request->file('file_produk');

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

        $result = $tab === 'produk'
            ? $uploadService->handleProdukUpload($fileArray, auth()->id())
            : $uploadService->handleUpload($fileArray, auth()->id());

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        if ($result['ok']) {
            return redirect()->route('admin.upload-history', ['id' => $result['id_upload']])
                ->with('success', 'File berhasil diupload. Preprocessing sedang berjalan.');
        }

        return back()->with('error', $result['pesan'] ?? 'Upload gagal.');
    }
}
