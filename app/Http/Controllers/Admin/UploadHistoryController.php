<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UploadHistoryController extends Controller
{
    public function index(Request $request)
    {
        $id_upload = (int) $request->get('id', 0);
        $filter    = $request->get('filter', 'all');

        if ($id_upload > 0) {
            $detail = DB::table('data_uploads as d')
                ->join('users as u', 'd.id_user', '=', 'u.id_user')
                ->where('d.id_upload', $id_upload)
                ->select('d.*', 'u.nama as nama_admin')
                ->first();

            if (!$detail) {
                $riwayat = DB::table('data_uploads as d')
                    ->join('users as u', 'd.id_user', '=', 'u.id_user')
                    ->select('d.*', 'u.nama as nama_admin')
                    ->orderByDesc('d.uploaded_at')
                    ->paginate(15);

                return view('admin.upload-history', [
                    'id_upload'        => $id_upload,
                    'detail'           => null,
                    'logs'             => [],
                    'filter'           => $filter,
                    'riwayat'          => $riwayat,
                    'page_list'        => $riwayat->currentPage(),
                    'total_pages_list' => $riwayat->lastPage(),
                    'count_all'        => $riwayat->total(),
                ]);
            }

            $logsQuery = DB::table('upload_logs')
                ->where('id_upload', $id_upload);

            if ($filter !== 'all') {
                $logsQuery->where('status_baris', $filter);
            }

            $logs = $logsQuery->orderBy('nomor_baris')->get();
            $riwayat = DB::table('data_uploads as d')
                ->join('users as u', 'd.id_user', '=', 'u.id_user')
                ->select('d.*', 'u.nama as nama_admin')
                ->orderByDesc('d.uploaded_at')
                ->paginate(15);

            return view('admin.upload-history', [
                'id_upload'        => $id_upload,
                'detail'           => $detail,
                'logs'             => $logs,
                'filter'           => $filter,
                'riwayat'          => $riwayat,
                'page_list'        => $riwayat->currentPage(),
                'total_pages_list' => $riwayat->lastPage(),
                'count_all'        => $riwayat->total(),
            ]);
        }

        // Daftar semua upload
        $riwayat = DB::table('data_uploads as d')
            ->join('users as u', 'd.id_user', '=', 'u.id_user')
            ->select('d.*', 'u.nama as nama_admin')
            ->orderByDesc('d.uploaded_at')
            ->paginate(15);
        $page_list       = $riwayat->currentPage();
        $total_pages_list = $riwayat->lastPage();
        $count_all        = $riwayat->total();

        return view('admin.upload-history', [
            'id_upload'        => 0,
            'detail'           => null,
            'logs'             => [],
            'filter'           => $filter,
            'riwayat'          => $riwayat,
            'page_list'        => $page_list,
            'total_pages_list' => $total_pages_list,
            'count_all'        => $count_all,
        ]);
    }

    public function destroy(int $id, UploadService $uploadService)
    {
        $result = $uploadService->deleteUpload($id);

        if (request()->expectsJson()) {
            return response()->json($result, $result['ok'] ? 200 : 422);
        }

        if ($result['ok']) {
            return redirect()->route('admin.upload-history')->with('success', $result['pesan']);
        }

        return back()->with('error', $result['pesan']);
    }
}
