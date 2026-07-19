@extends('layouts.admin')

@section('title', 'Kelola Produk — Toko Sinar Manis')

@section('content')
<div class="page-title-box mb-6 flex justify-between items-center">
    <div>
        <h1 style="font-size:1.75rem;font-weight:800;color:var(--primary);">Kelola Produk</h1>
        <p class="text-sm text-muted" style="margin-top:.25rem;">Tambah, ubah, dan pantau stok produk toko.</p>
    </div>
    <button onclick="adminModalOpen('modalAdd')" class="btn btn-sm" style="background:#e0e7ff; color:#3730a3; border:1px solid #c7d2fe;"><i class="fa-solid fa-plus"></i> Tambah Produk</button>
</div>

@if(session('success'))
    <div class="alert-card mb-6" style="background: #dcfce7; border-color: #bbf7d0;">
        <div class="alert-card-left">
            <i class="fa-solid fa-check-circle" style="color: #166534; font-size: 1.1rem; margin-top: 2px;"></i>
            <div>
                <div class="alert-card-title" style="color: #166534;">Berhasil!</div>
                <div class="alert-card-desc" style="color: #15803d;">{{ session('success') }}</div>
            </div>
        </div>
    </div>
@endif

@if(session('error'))
    <div class="alert-card mb-6" style="background: #fee2e2; border-color: #fecaca;">
        <div class="alert-card-left">
            <i class="fa-solid fa-circle-exclamation" style="color: #b91c1c; font-size: 1.1rem; margin-top: 2px;"></i>
            <div>
                <div class="alert-card-title" style="color: #b91c1c;">Gagal Dihapus!</div>
                <div class="alert-card-desc" style="color: #991b1b;">{{ session('error') }}</div>
            </div>
        </div>
    </div>
@endif

<div class="card mb-6">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.produk') }}" style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            <div style="flex:1; min-width: 200px;">
                <label class="form-label">Cari Produk</label>
                <input type="text" name="q" class="form-control" placeholder="Nama produk..." value="{{ request('q') }}">
            </div>
            <div>
                <label class="form-label">Kategori</label>
                <select name="id_category" class="form-control">
                    <option value="">Semua</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id_category }}" {{ request('id_category') == $c->id_category ? 'selected' : '' }}>{{ $c->nama_category }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">Semua</option>
                    <option value="aktif" {{ request('status') == 'aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="nonaktif" {{ request('status') == 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="{{ route('admin.produk') }}" class="btn btn-sm btn-outline"><i class="fa-solid fa-undo"></i> Reset</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-overflow">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kategori</th>
                    <th>Nama Produk</th>
                    <th>Harga</th>
                    <th>Stok</th>
                    <th>Terjual</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $p)
                <tr>
                    <td>{{ $p->id_product }}</td>
                    <td><span class="badge badge-navy">{{ $p->nama_category }}</span></td>
                    <td class="font-bold">{{ $p->nama_product }}</td>
                    <td>{{ \App\Helpers\Helpers::formatRupiah($p->harga) }}</td>
                    <td>
                        @if ($p->stok <= 0)
                            <span class="badge badge-red">Habis</span>
                        @elseif ($p->stok < 10)
                            <span style="color: #ef4444; font-weight: 700;">{{ $p->stok }}</span>
                        @else
                            <span style="color: #10b981; font-weight: 700;">{{ $p->stok }}</span>
                        @endif
                    </td>
                    <td>{{ $p->terjual }}</td>
                    <td>
                        @if($p->status == 'aktif')
                            <span class="badge badge-green">Aktif</span>
                        @else
                            <span class="badge badge-red">Nonaktif</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex justify-center gap-2">
                            <button onclick="editProduct({{ json_encode($p) }})" class="btn btn-sm btn-outline"><i class="fa-solid fa-edit"></i> Edit</button>
                            <form method="POST" action="{{ route('admin.produk.destroy', $p->id_product) }}" style="display:inline;" onsubmit="event.preventDefault(); confirmDeleteProduct(this);">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca;"><i class="fa-solid fa-trash"></i> Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <div style="border-top: 1px solid var(--border-color); background: var(--color-white); border-bottom-left-radius: var(--radius-card); border-bottom-right-radius: var(--radius-card);">
        {!! \App\Helpers\Helpers::renderPagination($currentPage, $totalPages, request()->query()) !!}
    </div>
</div>

<!-- Modal Tambah -->
<div id="modalAdd" class="modal-overlay">
    <div class="modal-card">
        <div class="card-body">
            <h3 class="mb-4">Tambah Produk</h3>
            <form method="POST" action="{{ route('admin.produk.store') }}" onsubmit="adminShowLoading('Menyimpan produk...')">
                @csrf
                <div class="form-group">
                    <label class="form-label">Nama Produk</label>
                    <input type="text" name="nama_product" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="id_category" class="form-control" required>
                        @foreach($categories as $c)
                            <option value="{{ $c->id_category }}">{{ $c->nama_category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Harga (Rp)</label>
                        <input type="number" name="harga" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stok Awal</label>
                        <input type="number" name="stok" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="3"></textarea>
                </div>
                <div class="flex justify-between mt-4">
                    <button type="button" onclick="adminModalClose('modalAdd')" class="btn btn-sm btn-outline"><i class="fa-solid fa-times"></i> Batal</button>
                    <button type="submit" class="btn btn-sm" style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0;"><i class="fa-solid fa-save"></i> Simpan Produk</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" class="modal-overlay">
    <div class="modal-card">
        <div class="card-body">
            <h3 class="mb-4">Edit Produk</h3>
            <form method="POST" id="formEdit" onsubmit="adminShowLoading('Menyimpan perubahan...')">
                @csrf
                @method('PUT')
                <input type="hidden" name="id_product" id="edit_id_product">
                <div class="form-group">
                    <label class="form-label">Nama Produk</label>
                    <input type="text" name="nama_product" id="edit_nama_product" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="id_category" id="edit_id_category" class="form-control" required>
                        @foreach($categories as $c)
                            <option value="{{ $c->id_category }}">{{ $c->nama_category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Harga (Rp)</label>
                        <input type="number" name="harga" id="edit_harga" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stok</label>
                        <input type="number" name="stok" id="edit_stok" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit_status" class="form-control" required>
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
                <div class="flex justify-between mt-4">
                    <button type="button" onclick="adminModalClose('modalEdit')" class="btn btn-sm btn-outline"><i class="fa-solid fa-times"></i> Batal</button>
                    <button type="submit" class="btn btn-sm" style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0;"><i class="fa-solid fa-save"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function editProduct(p) {
    document.getElementById('edit_id_product').value = p.id_product;
    document.getElementById('edit_nama_product').value = p.nama_product;
    document.getElementById('edit_id_category').value = p.id_category;
    document.getElementById('edit_harga').value = p.harga;
    document.getElementById('edit_stok').value = p.stok;
    document.getElementById('edit_deskripsi').value = p.deskripsi || '';
    document.getElementById('edit_status').value = p.status;
    document.getElementById('formEdit').action = "{{ route('admin.produk.update', '__ID__') }}".replace('__ID__', p.id_product);
    adminModalOpen('modalEdit');
}
</script>
@endpush
