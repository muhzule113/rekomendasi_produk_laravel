<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.laporan', [
            'tanggalAwal' => $request->get('tanggal_awal', ''),
            'tanggalAkhir' => $request->get('tanggal_akhir', ''),
        ]);
    }
}
