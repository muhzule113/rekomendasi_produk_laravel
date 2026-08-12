<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        $totalPages = $products->lastPage();
        $categories = DB::table('categories')->get();

        return view('admin.produk', compact('products', 'currentPage', 'totalPages', 'categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->productRules($request));

        $data = [
            'nama_product' => $validated['nama_product'],
            'harga' => $validated['harga'],
            'stok' => $validated['stok'],
            'deskripsi' => $validated['deskripsi'] ?? null,
        ];

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('products', 'public');
        }

        DB::transaction(function () use (&$data, $validated): void {
            $data['id_category'] = $this->resolveCategoryId($validated);
            DB::table('products')->insert($data);
        });

        return redirect()->route('admin.produk')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate($this->productRules($request, true));

        $data = [
            'nama_product' => $validated['nama_product'],
            'harga' => $validated['harga'],
            'stok' => $validated['stok'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'status' => $validated['status'],
        ];

        if ($request->hasFile('foto')) {
            $old = DB::table('products')->where('id_product', $id)->value('foto');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            $data['foto'] = $request->file('foto')->store('products', 'public');
        }

        DB::transaction(function () use (&$data, $validated, $id): void {
            $data['id_category'] = $this->resolveCategoryId($validated);
            DB::table('products')->where('id_product', $id)->update($data);
        });

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

    private function productRules(Request $request, bool $updating = false): array
    {
        return [
            'nama_product' => ['required', 'string', 'max:150'],
            'id_category' => [
                'required',
                Rule::when(
                    $request->input('id_category') === '__new__',
                    ['in:__new__'],
                    ['integer', 'exists:categories,id_category']
                ),
            ],
            'new_category_name' => ['nullable', 'required_if:id_category,__new__', 'string', 'max:100'],
            'harga' => ['required', 'numeric', 'min:0'],
            'stok' => ['required', 'integer', 'min:0'],
            'foto' => ['nullable', 'image', 'max:2048'],
            'deskripsi' => ['nullable', 'string'],
            'status' => [$updating ? 'required' : 'nullable', Rule::in(['aktif', 'nonaktif'])],
        ];
    }

    private function resolveCategoryId(array $validated): int
    {
        if ($validated['id_category'] !== '__new__') {
            return (int) $validated['id_category'];
        }

        $categoryName = trim($validated['new_category_name']);
        $existingId = DB::table('categories')
            ->whereRaw('LOWER(nama_category) = ?', [Str::lower($categoryName)])
            ->value('id_category');

        return $existingId
            ? (int) $existingId
            : (int) DB::table('categories')->insertGetId([
                'nama_category' => $categoryName,
            ], 'id_category');
    }
}
