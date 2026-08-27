@extends('layouts.admin')

@section('title', 'Kelola Kategori — Toko Sinar Manis')

@section('content')
<div class="page-title-box mb-6 flex justify-between items-center">
    <div>
        <h1 style="font-size:1.75rem;font-weight:800;color:var(--primary);">Kelola Kategori</h1>
        <p class="text-sm text-muted" style="margin-top:.25rem;">Tambah, ubah, dan hapus jenis kategori produk.</p>
    </div>
    <a href="{{ route('admin.produk') }}" class="btn btn-sm btn-outline"><i class="fa-solid fa-box"></i> Kelola Produk</a>
</div>

@if(session('success'))
    <div class="alert-card mb-6" style="background:#dcfce7; border-color:#bbf7d0;">
        <div class="alert-card-left">
            <i class="fa-solid fa-check-circle" style="color:#166534; font-size:1.1rem; margin-top:2px;"></i>
            <div>
                <div class="alert-card-title" style="color:#166534;">Berhasil!</div>
                <div class="alert-card-desc" style="color:#15803d;">{{ session('success') }}</div>
            </div>
        </div>
    </div>
@endif

@if(session('error'))
    <div class="alert-card mb-6" style="background:#fee2e2; border-color:#fecaca;">
        <div class="alert-card-left">
            <i class="fa-solid fa-circle-exclamation" style="color:#b91c1c; font-size:1.1rem; margin-top:2px;"></i>
            <div>
                <div class="alert-card-title" style="color:#b91c1c;">Tidak dapat menghapus</div>
                <div class="alert-card-desc" style="color:#991b1b;">{{ session('error') }}</div>
            </div>
        </div>
    </div>
@endif

@if($errors->any())
    <div class="alert-card mb-6" style="background:#fee2e2; border-color:#fecaca;">
        <div class="alert-card-left">
            <i class="fa-solid fa-circle-exclamation" style="color:#b91c1c; font-size:1.1rem; margin-top:2px;"></i>
            <div>
                <div class="alert-card-title" style="color:#b91c1c;">Data belum dapat disimpan</div>
                <div class="alert-card-desc" style="color:#991b1b;">{{ $errors->first() }}</div>
            </div>
        </div>
    </div>
@endif

<div class="card mb-6">
    <div class="card-body">
        <div style="margin-bottom:1rem;">
            <h2 style="margin:0; color:var(--color-navy); font-size:1.1rem;">Tambah Kategori</h2>
            <p class="text-sm text-muted" style="margin:.25rem 0 0;">Kategori ini bisa langsung dipilih saat menambah atau mengedit produk.</p>
        </div>
        <form method="POST" action="{{ route('admin.kategori.store') }}" style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            @csrf
            <div style="flex:1; min-width:220px;">
                <label class="form-label" for="nama_category">Nama Kategori</label>
                <input type="text" id="nama_category" name="nama_category" class="form-control" value="{{ old('nama_category') }}" maxlength="100" required>
            </div>
            <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> Tambah Kategori</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-overflow">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Kategori</th>
                    <th>Jumlah Produk</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td>{{ $category->id_category }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.kategori.update', $category->id_category) }}" style="display:flex; gap:.5rem; align-items:center; min-width:280px;">
                                @csrf
                                @method('PUT')
                                <label class="sr-only" for="category_{{ $category->id_category }}">Nama kategori {{ $category->nama_category }}</label>
                                <input type="text" id="category_{{ $category->id_category }}" name="nama_category" class="form-control" value="{{ $category->nama_category }}" maxlength="100" required>
                                <button type="submit" class="btn btn-sm btn-outline" title="Simpan perubahan"><i class="fa-solid fa-save"></i><span class="sr-only">Simpan</span></button>
                            </form>
                        </td>
                        <td>{{ $category->products_count }}</td>
                        <td class="text-center">
                            @if($category->products_count > 0)
                                <span class="text-muted" title="Pindahkan produk ke kategori lain terlebih dahulu.">Masih digunakan</span>
                            @else
                                <form method="POST" action="{{ route('admin.kategori.destroy', $category->id_category) }}" onsubmit="return confirm('Hapus kategori ini?');" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca;"><i class="fa-solid fa-trash"></i> Hapus</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted">Belum ada kategori.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
