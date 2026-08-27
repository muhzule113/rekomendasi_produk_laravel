<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KategoriController extends Controller
{
    public function index()
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('nama_category')
            ->get();

        return view('admin.kategori', compact('categories'));
    }

    public function store(Request $request)
    {
        $name = $this->validatedName($request);
        Category::create(['nama_category' => $name]);

        return redirect()->route('admin.kategori')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(Request $request, Category $category)
    {
        $category->update(['nama_category' => $this->validatedName($request, $category)]);

        return redirect()->route('admin.kategori')->with('success', 'Kategori berhasil diupdate.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return redirect()->route('admin.kategori')->with('error', 'Kategori tidak bisa dihapus karena masih digunakan oleh produk.');
        }

        try {
            $category->delete();
        } catch (\Throwable) {
            return redirect()->route('admin.kategori')->with('error', 'Kategori tidak bisa dihapus karena masih digunakan oleh produk.');
        }

        return redirect()->route('admin.kategori')->with('success', 'Kategori berhasil dihapus.');
    }

    private function validatedName(Request $request, ?Category $category = null): string
    {
        $request->merge(['nama_category' => trim((string) $request->input('nama_category'))]);

        $validated = $request->validate([
            'nama_category' => [
                'required',
                'string',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail) use ($category): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $query = Category::query()->whereRaw('LOWER(nama_category) = ?', [Str::lower($value)]);

                    if ($category) {
                        $query->where('id_category', '!=', $category->getKey());
                    }

                    if ($query->exists()) {
                        $fail('Nama kategori sudah digunakan.');
                    }
                },
            ],
        ]);

        return $validated['nama_category'];
    }
}
