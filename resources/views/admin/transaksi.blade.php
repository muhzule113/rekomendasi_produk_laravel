@extends('layouts.admin')

@section('title', 'Kelola Transaksi — Toko Sinar Manis')

@section('content')
<div class="page-title-box mb-6">
    <h1 style="font-size:1.75rem;font-weight:800;color:var(--primary);">Kelola Transaksi</h1>
    <p class="text-sm text-muted" style="margin-top:.25rem;">Pantau dan perbarui status pesanan pelanggan secara langsung.</p>
</div>

@if(session('success'))
    <div class="alert-card mb-6" style="background: #dcfce7; border-color: #bbf7d0;">
        <div class="alert-card-left">
            <i class="fa-solid fa-circle-check" style="color: #166534; font-size: 1.1rem; margin-top: 2px;"></i>
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
            <i class="fa-solid fa-triangle-exclamation" style="color: #b91c1c; font-size: 1.1rem; margin-top: 2px;"></i>
            <div>
                <div class="alert-card-title" style="color: #b91c1c;">Gagal!</div>
                <div class="alert-card-desc" style="color: #991b1b;">{{ session('error') }}</div>
            </div>
        </div>
    </div>
@endif

<div class="card mb-6">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.transaksi') }}" style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            <div style="flex:1; min-width: 200px;">
                <label class="form-label">Cari Trx / Pelanggan</label>
                <input type="text" name="q" class="form-control" placeholder="Cari..." value="{{ request('q') }}">
            </div>
            <div>
                <label class="form-label">Status Pembayaran</label>
                <select name="status_pembayaran" class="form-control">
                    <option value="">Semua</option>
                    <option value="Belum Dibayar" {{ request('status_pembayaran') == 'Belum Dibayar' ? 'selected' : '' }}>Belum Dibayar</option>
                    <option value="Dibayar" {{ request('status_pembayaran') == 'Dibayar' ? 'selected' : '' }}>Dibayar</option>
                </select>
            </div>
            <div>
                <label class="form-label">Status Pesanan</label>
                <select name="status_pesanan" class="form-control">
                    <option value="">Semua</option>
                    <option value="Diproses" {{ request('status_pesanan') == 'Diproses' ? 'selected' : '' }}>Diproses</option>
                    <option value="Selesai" {{ request('status_pesanan') == 'Selesai' ? 'selected' : '' }}>Selesai</option>
                    <option value="Dibatalkan" {{ request('status_pesanan') == 'Dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="{{ route('admin.transaksi') }}" class="btn btn-sm btn-outline"><i class="fa-solid fa-undo"></i> Reset</a>
        </form>
    </div>
</div>

<div class="card">
    <div id="bulk-action-bar" style="display:none; padding: .75rem 1rem; border-bottom: 1px solid var(--border-color); background: #fef2f2; align-items: center; gap: .75rem;">
        <span class="text-sm" style="color:#991b1b;">Terpilih: <strong id="bulk-count">0</strong></span>
        <button type="button" class="btn btn-sm" style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca;" onclick="submitBulkDelete('formBulkTransaksi', { title: 'Hapus Transaksi Terpilih?', desc: 'Transaksi yang dihapus tidak dapat dikembalikan.', okLabel: 'Hapus Transaksi', loadingText: 'Menghapus transaksi...' })">
            <i class="fa-solid fa-trash"></i> Hapus Terpilih
        </button>
    </div>
    <div class="table-overflow">
        <table>
            <thead>
                <tr>
                    <th style="width:40px;"><input type="checkbox" id="checkAllTransaksi" onchange="bulkToggleAll('checkAllTransaksi', 'ids[]')"></th>
                    <th>ID Trx</th>
                    <th>Pelanggan</th>
                    <th>Tanggal</th>
                    <th>Total</th>
                    <th>Metode</th>
                    <th>Status Pembayaran</th>
                    <th>Status Pesanan</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @if(empty($transactions))
                <tr><td colspan="9" class="text-center py-4">Tidak ada data transaksi.</td></tr>
                @else
                    @foreach($transactions as $t)
                    <tr>
                        <td><input type="checkbox" class="bulk-check" name="ids[]" value="{{ $t->id_transaction }}" onchange="bulkUpdateBar('ids[]')"></td>
                        <td class="font-bold">#TRX-{{ str_pad($t->id_transaction, 5, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $t->nama }}</td>
                        <td>{{ date('d M Y H:i', strtotime($t->tanggal)) }}</td>
                        <td class="font-bold text-navy">{{ \App\Helpers\Helpers::formatRupiah($t->total) }}</td>
                        <td>{{ $t->metode_pembayaran }}</td>
                        <td>
                            @if($t->metode_pembayaran === 'Midtrans' || $t->status_pembayaran === 'Dibayar')
                                <span class="badge badge-{{ $t->status_pembayaran == 'Dibayar' ? 'success' : 'warning' }}">
                                    {{ $t->status_pembayaran }}
                                </span>
                            @else
                                <div style="display:flex; gap: 0.5rem; align-items:center;">
                                    <select id="pembayaran_{{ $t->id_transaction }}" class="form-control" style="padding: 0.2rem 0.5rem; width: auto; height: 32px; font-size: 0.85rem;">
                                        <option value="Belum Dibayar" {{ $t->status_pembayaran == 'Belum Dibayar' ? 'selected' : '' }}>Belum Dibayar</option>
                                        <option value="Dibayar" {{ $t->status_pembayaran == 'Dibayar' ? 'selected' : '' }}>Dibayar</option>
                                    </select>
                                    <button type="button" class="btn btn-sm btn-outline" onclick="confirmStatusUpdate({{ $t->id_transaction }}, 'update_pembayaran', document.getElementById('pembayaran_{{ $t->id_transaction }}').value, '{{ addslashes($t->status_pembayaran) }}')" title="Simpan">
                                        <i class="fa-solid fa-save"></i>
                                    </button>
                                </div>
                            @endif
                        </td>
                        <td>
                            @if(in_array($t->status_pesanan, ['Selesai', 'Dibatalkan']))
                                <span class="badge badge-{{ $t->status_pesanan == 'Selesai' ? 'green' : 'red' }}">
                                    {{ $t->status_pesanan }}
                                </span>
                            @else
                                <div style="display:flex; gap: 0.5rem; align-items:center;">
                                    <select id="pesanan_{{ $t->id_transaction }}" class="form-control" style="padding: 0.2rem 0.5rem; width: auto; height: 32px; font-size: 0.85rem;">
                                        <option value="Diproses" {{ $t->status_pesanan == 'Diproses' ? 'selected' : '' }}>Diproses</option>
                                        <option value="Selesai" {{ $t->status_pesanan == 'Selesai' ? 'selected' : '' }}>Selesai</option>
                                        <option value="Dibatalkan" {{ $t->status_pesanan == 'Dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                                    </select>
                                    <button type="button" class="btn btn-sm btn-outline" onclick="confirmStatusUpdate({{ $t->id_transaction }}, 'update_pesanan', document.getElementById('pesanan_{{ $t->id_transaction }}').value, '{{ addslashes($t->status_pesanan) }}')" title="Simpan">
                                        <i class="fa-solid fa-save"></i>
                                    </button>
                                </div>
                            @endif
                        </td>
                        <td class="text-right">
                            <button type="button" class="btn btn-outline btn-sm" onclick="showDetailTrx({{ $t->id_transaction }})"><i class="fa-solid fa-eye"></i> Detail</button>
                        </td>
                    </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <div style="border-top: 1px solid var(--border-color); background: var(--color-white); border-bottom-left-radius: var(--radius-card); border-bottom-right-radius: var(--radius-card);">
        {!! \App\Helpers\Helpers::renderPagination($currentPage, $totalPages, request()->query()) !!}
    </div>
</div>

<form id="formBulkTransaksi" method="POST" action="{{ route('admin.transaksi.bulk-destroy') }}" style="display:none;">
    @csrf
    @method('DELETE')
</form>

<!-- Hidden form for status update -->
<form id="formUpdateStatus" method="POST" action="" style="display:none;">
    @csrf
    <input type="hidden" name="action" id="form_action">
    <input type="hidden" name="id_transaction" id="form_id_transaction">
    <input type="hidden" name="new_status" id="form_new_status">
</form>

<!-- Modal Detail Transaksi -->
<div class="modal-overlay" id="modal-detail-trx" onclick="adminModalClose('modal-detail-trx')">
    <div class="modal-card" style="max-width: 700px;" onclick="event.stopPropagation()">
        <div style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin:0; font-size:1.25rem; font-family:var(--font-heading); color:var(--color-navy); font-weight:800;">
                    Detail Transaksi <span id="detail-trx-id" style="color:var(--color-gold);"></span>
                </h3>
            </div>

            <div id="detail-loading" style="text-align:center; padding: 2rem;">
                <i class="fa-solid fa-spinner fa-spin fa-2x text-muted"></i>
            </div>

            <div id="detail-content" style="display:none;">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem; padding:1rem; background:var(--color-gray-50); border-radius:8px;">
                    <div><span class="text-muted" style="font-size:0.85rem;">Total Pembayaran</span><br><strong id="detail-total"></strong></div>
                    <div><span class="text-muted" style="font-size:0.85rem;">Metode Pembayaran</span><br><strong id="detail-metode"></strong></div>
                    <div><span class="text-muted" style="font-size:0.85rem;">Status Pembayaran</span><br><strong id="detail-stat-pembayaran"></strong></div>
                    <div><span class="text-muted" style="font-size:0.85rem;">Status Pesanan</span><br><strong id="detail-stat-pesanan"></strong></div>
                </div>

                <h4 style="margin-bottom: 0.5rem; font-size:1rem;">Daftar Produk</h4>
                <div class="table-overflow" style="max-height: 250px; overflow-y: auto; box-shadow: inset 0 0 0 1px var(--color-gray-100);">
                    <table style="font-size:0.9rem;">
                        <thead style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th>Produk</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Harga</th>
                                <th class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="detail-tbody">
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.25rem;">
                <button type="button" class="btn btn-outline btn-sm" onclick="adminModalClose('modal-detail-trx')"><i class="fa-solid fa-times"></i> Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function confirmStatusUpdate(id, action, newStatus, oldStatus) {
    if (newStatus === oldStatus) {
        adminToast('Tidak ada perubahan status.', 'info');
        return;
    }

    let title = action === 'update_pembayaran' ? 'Ubah Status Pembayaran?' : 'Ubah Status Pesanan?';
    let desc = {
        before: 'Yakin ingin mengubah status menjadi ',
        emphasis: newStatus,
        after: '?',
        note: newStatus === 'Selesai'
            ? 'Status ini bersifat final dan tidak dapat diubah kembali.'
            : newStatus === 'Dibatalkan'
                ? 'Aksi pembatalan tidak dapat dikembalikan.'
                : '',
    };

    if (typeof adminConfirm === 'function') {
        adminConfirm({
            type: newStatus === 'Dibatalkan' ? 'danger' : 'warning',
            title: title,
            desc: desc,
            okLabel: 'Ya, Ubah',
            onConfirm: () => {
                document.getElementById('form_action').value = action;
                document.getElementById('form_id_transaction').value = id;
                document.getElementById('form_new_status').value = newStatus;
                document.getElementById('formUpdateStatus').action = "{{ route('admin.transaksi.status', '__ID__') }}".replace('__ID__', id);
                adminShowLoading('Menyimpan perubahan...');
                document.getElementById('formUpdateStatus').submit();
            }
        });
    } else {
        if(confirm(`Yakin ingin mengubah status menjadi ${newStatus}?`)) {
            document.getElementById('form_action').value = action;
            document.getElementById('form_id_transaction').value = id;
            document.getElementById('form_new_status').value = newStatus;
            document.getElementById('formUpdateStatus').action = "{{ route('admin.transaksi.status', '__ID__') }}".replace('__ID__', id);
            document.getElementById('formUpdateStatus').submit();
        }
    }
}

async function showDetailTrx(idTrx) {
    adminModalOpen('modal-detail-trx');
    document.getElementById('detail-loading').style.display = 'block';
    document.getElementById('detail-content').style.display = 'none';
    document.getElementById('detail-trx-id').textContent = `#TRX-${String(idTrx).padStart(5, '0')}`;

    try {
        const res = await fetch("{{ route('admin.transaksi.detail', '__ID__') }}".replace('__ID__', idTrx));
        const data = await res.json();

        document.getElementById('detail-total').textContent = 'Rp ' + parseInt(data.trx.total).toLocaleString('id-ID');
        document.getElementById('detail-metode').textContent = data.trx.metode_pembayaran;
        document.getElementById('detail-stat-pembayaran').textContent = data.trx.status_pembayaran;
        document.getElementById('detail-stat-pesanan').textContent = data.trx.status_pesanan;

        const tbody = document.getElementById('detail-tbody');
        tbody.innerHTML = '';

        data.items.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.nama_product}</td>
                <td class="text-center">${item.qty}</td>
                <td class="text-right">Rp ${parseInt(item.harga).toLocaleString('id-ID')}</td>
                <td class="text-right font-bold">Rp ${parseInt(item.subtotal).toLocaleString('id-ID')}</td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('detail-loading').style.display = 'none';
        document.getElementById('detail-content').style.display = 'block';
    } catch(e) {
        adminToast('Gagal memuat detail', 'error');
        adminModalClose('modal-detail-trx');
    }
}
</script>
@endpush
