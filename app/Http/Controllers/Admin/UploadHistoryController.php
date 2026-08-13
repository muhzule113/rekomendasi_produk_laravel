<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UploadHistoryController extends Controller
{
    public function legacy(Request $request)
    {
        $idUpload = (int) $request->query('id', 0);
        $source = $request->query('sumber') === 'produk' || $request->query('tab') === 'produk'
            ? 'produk'
            : null;

        if ($idUpload > 0) {
            $source = DB::table('data_uploads')
                ->where('id_upload', $idUpload)
                ->value('sumber') ?: $source;
        }

        $route = $source === 'produk'
            ? 'admin.upload-history.produk'
            : 'admin.upload-history.transaksi';

        return redirect()->route($route, $idUpload > 0 ? ['id' => $idUpload] : []);
    }

    public function transaksi(Request $request, ?int $id = null)
    {
        return $this->showHistory($request, 'transaksi', $id);
    }

    public function produk(Request $request, ?int $id = null)
    {
        return $this->showHistory($request, 'produk', $id);
    }

    private function showHistory(Request $request, string $source, ?int $id = null)
    {
        $idUpload = $id ?? (int) $request->query('id', 0);
        $filter = $request->query('filter', 'all');

        $detail = null;
        $logs = [];

        if ($idUpload > 0) {
            $detail = $this->historyQuery($source)
                ->where('d.id_upload', $idUpload)
                ->first();

            if ($detail) {
                $logsQuery = DB::table('upload_logs')
                    ->where('id_upload', $idUpload);

                if ($filter !== 'all') {
                    $logsQuery->where('status_baris', $filter);
                }

                $logs = $logsQuery->orderBy('nomor_baris')->get();
            }
        }

        $riwayat = $this->historyQuery($source)
            ->orderByDesc('d.uploaded_at')
            ->paginate(15, ['*'], 'page_list')
            ->appends($request->except(['id', 'page_list']));

        return view('admin.upload-history', [
            'sumber'           => $source,
            'id_upload'        => $idUpload,
            'detail'           => $detail,
            'logs'             => $logs,
            'filter'           => $filter,
            'riwayat'          => $riwayat,
            'page_list'        => $riwayat->currentPage(),
            'total_pages_list' => $riwayat->lastPage(),
            'count_all'        => $riwayat->total(),
        ]);
    }

    private function historyQuery(string $source)
    {
        return DB::table('data_uploads as d')
            ->join('users as u', 'd.id_user', '=', 'u.id_user')
            ->where('d.sumber', $source)
            ->select('d.*', 'u.nama as nama_admin');
    }

    public function destroy(int $id, UploadService $uploadService)
    {
        $source = DB::table('data_uploads')
            ->where('id_upload', $id)
            ->value('sumber');
        $result = $uploadService->deleteUpload($id);

        if (request()->expectsJson()) {
            return response()->json($result, $result['ok'] ? 200 : 422);
        }

        if ($result['ok']) {
            return redirect()->route(
                $source === 'produk' ? 'admin.upload-history.produk' : 'admin.upload-history.transaksi'
            )->with('success', $result['pesan']);
        }

        return back()->with('error', $result['pesan']);
    }
}
