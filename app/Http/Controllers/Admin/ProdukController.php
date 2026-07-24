<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        $request->validate([
            'foto' => 'nullable|image|max:2048',
        ]);

        $data = [
            'nama_product' => $request->input('nama_product'),
            'id_category'  => $request->input('id_category'),
            'harga'        => $request->input('harga'),
            'stok'         => $request->input('stok'),
            'deskripsi'    => $request->input('deskripsi'),
        ];

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('products', 'public');
        }

        DB::table('products')->insert($data);

        return redirect()->route('admin.produk')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'foto' => 'nullable|image|max:2048',
        ]);

        $data = [
            'nama_product' => $request->input('nama_product'),
            'id_category'  => $request->input('id_category'),
            'harga'        => $request->input('harga'),
            'stok'         => $request->input('stok'),
            'deskripsi'    => $request->input('deskripsi'),
            'status'       => $request->input('status'),
        ];

        if ($request->hasFile('foto')) {
            $old = DB::table('products')->where('id_product', $id)->value('foto');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            $data['foto'] = $request->file('foto')->store('products', 'public');
        }

        DB::table('products')->where('id_product', $id)->update($data);

        return redirect()->route('admin.produk')->with('success', 'Produk berhasil diupdate.');
    }

    public function destroy($id)
    {
        try {
            $foto = DB::table('products')->where('id_product', $id)->value('foto');
            DB::table('products')->where('id_product', $id)->delete();
            if ($foto) {
                Storage::disk('public')->delete($foto);
            }
            return redirect()->route('admin.produk')->with('success', 'Produk berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->route('admin.produk')->with('error', 'Produk tidak bisa dihapus karena sudah ada di riwayat transaksi.');
        }
    }

    public function bulkDestroy(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []));
        if (empty($ids)) {
            return redirect()->route('admin.produk')->with('error', 'Pilih minimal satu produk.');
        }

        $deleted = 0;
        $failed = 0;
        foreach ($ids as $id) {
            try {
                DB::table('products')->where('id_product', $id)->delete();
                $deleted++;
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return redirect()->route('admin.produk')->with(
            $deleted > 0 ? 'success' : 'error',
            $deleted > 0
                ? ($failed > 0
                    ? "{$deleted} produk dihapus. {$failed} gagal karena sudah ada di riwayat transaksi."
                    : "{$deleted} produk berhasil dihapus.")
                : 'Produk tidak bisa dihapus karena sudah ada di riwayat transaksi.'
        );
    }
}
