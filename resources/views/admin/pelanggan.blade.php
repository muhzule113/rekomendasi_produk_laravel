@extends('layouts.admin')

@section('title', 'Data Pelanggan — Toko Sinar Manis')

@section('content')
<div class="page-title-box mb-6">
    <h1 style="font-size:1.75rem;font-weight:800;color:var(--primary);">Data Pelanggan</h1>
    <p class="text-sm text-muted" style="margin-top:.25rem;">Kelola dan pantau informasi pelanggan serta jumlah riwayat transaksinya.</p>
</div>

<div class="card">
    <div class="table-overflow">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Lengkap</th>
                    <th>Email</th>
                    <th>Nomor HP</th>
                    <th>Alamat</th>
                    <th class="text-center">Total Transaksi</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($customers as $c)
                <tr>
                    <td>{{ $c->id_user }}</td>
                    <td class="font-bold">{{ $c->nama }}</td>
                    <td>{{ $c->email }}</td>
                    <td>{{ $c->no_hp }}</td>
                    <td>{{ $c->alamat }}</td>
                    <td class="text-center"><span class="badge badge-navy" style="font-size: 0.75rem;">{{ $c->total_transaksi }} Transaksi</span></td>
                    <td>
                        @if($c->status == 'aktif')
                            <span class="badge badge-green">Aktif</span>
                        @else
                            <span class="badge badge-red">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <button class="btn btn-outline btn-sm" onclick="showDetail({{ $c->id_user }})"><i class="fa-solid fa-eye"></i> Detail</button>
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

<!-- Modal Detail -->
<div class="modal-overlay" id="modal-detail" onclick="adminModalClose('modal-detail')">
    <div class="modal-card" style="max-width: 650px;" onclick="event.stopPropagation()">
        <div style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin:0; font-size:1.25rem; font-family:var(--font-heading); color:var(--color-navy); font-weight:800;">
                    Riwayat Transaksi: <span id="detail-nama" style="color:var(--color-gold);"></span>
                </h3>
            </div>

            <div id="detail-loading" style="text-align:center; padding: 2rem;">
                <i class="fa-solid fa-spinner fa-spin fa-2x text-muted"></i>
            </div>

            <div id="detail-content" style="display:none;">
                <div class="table-overflow" style="max-height: 350px; overflow-y: auto; box-shadow: inset 0 0 0 1px var(--color-gray-100);">
                    <table>
                        <thead style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th>ID Trx</th>
                                <th>Tanggal</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="detail-tbody">
                        </tbody>
                    </table>
                </div>
                <div id="detail-empty" style="display:none; text-align:center; padding: 2rem; color: var(--muted-foreground);">
                    <i class="fa-solid fa-box-open" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                    <br>Belum ada riwayat transaksi.
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.25rem;">
                <button type="button" class="btn btn-outline btn-sm" onclick="adminModalClose('modal-detail')"><i class="fa-solid fa-times"></i> Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
async function showDetail(idUser) {
    adminModalOpen('modal-detail');
    document.getElementById('detail-loading').style.display = 'block';
    document.getElementById('detail-content').style.display = 'none';
    document.getElementById('detail-empty').style.display = 'none';

    try {
        const res = await fetch("{{ route('admin.pelanggan.transaksi', '__ID__') }}".replace('__ID__', idUser));
        const data = await res.json();

        document.getElementById('detail-nama').textContent = data.nama;

        const tbody = document.getElementById('detail-tbody');
        tbody.innerHTML = '';

        if (data.data.length === 0) {
            document.getElementById('detail-empty').style.display = 'block';
        } else {
            data.data.forEach(t => {
                const tr = document.createElement('tr');

                // Format tanggal
                const dateObj = new Date(t.tanggal);
                const tglFormatted = dateObj.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });

                let badgeClass = 'badge-navy';
                if (t.status_pesanan === 'Selesai') badgeClass = 'badge-green';
                if (t.status_pesanan === 'Dibatalkan') badgeClass = 'badge-red';
                if (t.status_pesanan === 'Diproses') badgeClass = 'badge-blue';

                tr.innerHTML = `
                    <td class="font-bold">#TRX-${String(t.id_transaction).padStart(5, '0')}</td>
                    <td>${tglFormatted}</td>
                    <td class="font-bold text-navy">Rp ${parseInt(t.total).toLocaleString('id-ID')}</td>
                    <td>
                        <span class="badge ${badgeClass}" style="font-size:0.75rem;">
                            ${t.status_pesanan}
                        </span>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        document.getElementById('detail-loading').style.display = 'none';
        document.getElementById('detail-content').style.display = 'block';

    } catch (e) {
        adminToast('Gagal mengambil data transaksi', 'error');
        adminModalClose('modal-detail');
    }
}
</script>
@endpush
