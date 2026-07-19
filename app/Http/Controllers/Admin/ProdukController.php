<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProdukController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('products as p')
            ->join('categories as c', 'p.id_category', '=', 'c.id_category')
            ->select('p.*', 'c.nama_category');

        if ($q = $request->get('q')) {
            $query->where('p.nama_product', 'like', "%{$q}%");
        }
        if ($cat = $request->get('id_category')) {
            $query->where('p.id_category', $cat);
        }
        if ($status = $request->get('status')) {
            $query->where('p.status', $status);
        }

        $products = $query->orderByDesc('p.id_product')->paginate(10)->appends($request->all());
        $currentPage = $products->currentPage();
        $totalPages  = $products->lastPage();
        $categories  = DB::table('categories')->get();

        return view('admin.produk', compact('products', 'currentPage', 'totalPages', 'categories'));
    }

    public function store(Request $request)
    {
        DB::table('products')->insert([
            'nama_product' => $request->input('nama_product'),
            'id_category'  => $request->input('id_category'),
            'harga'        => $request->input('harga'),
            'stok'         => $request->input('stok'),
            'deskripsi'    => $request->input('deskripsi'),
        ]);

        return redirect()->route('admin.produk')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        DB::table('products')->where('id_product', $id)->update([
            'nama_product' => $request->input('nama_product'),
            'id_category'  => $request->input('id_category'),
            'harga'        => $request->input('harga'),
            'stok'         => $request->input('stok'),
            'deskripsi'    => $request->input('deskripsi'),
            'status'       => $request->input('status'),
        ]);

        return redirect()->route('admin.produk')->with('success', 'Produk berhasil diupdate.');
    }

    public function destroy($id)
    {
        try {
            DB::table('products')->where('id_product', $id)->delete();
            return redirect()->route('admin.produk')->with('success', 'Produk berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->route('admin.produk')->with('error', 'Produk tidak bisa dihapus karena sudah ada di riwayat transaksi.');
        }
    }
}
