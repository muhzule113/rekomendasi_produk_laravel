@extends('layouts.admin')

@section('title', 'Laporan Transaksi — Toko Sinar Manis')

@section('content')
<div class="page-title-box mb-6 flex justify-between items-center hide-on-print">
    <div>
        <h1 style="font-size:1.75rem;font-weight:800;color:var(--primary);">Laporan Transaksi</h1>
        <p class="text-sm text-muted" style="margin-top:.25rem;">Rekapitulasi riwayat transaksi dan pendapatan toko.</p>
    </div>
    <div class="flex gap-2">
        <button onclick="exportToPDF()" class="btn btn-sm" style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca;"><i class="fa-solid fa-file-pdf"></i> Export PDF</button>
        <button onclick="exportToExcel()" class="btn btn-sm" style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0;"><i class="fa-solid fa-file-excel"></i> Export Excel</button>
        <button onclick="window.print()" class="btn btn-sm" style="background:#e0e7ff; color:#3730a3; border:1px solid #c7d2fe;"><i class="fa-solid fa-print"></i> Cetak</button>
    </div>
</div>

<div class="card mb-6 hide-on-print">
    <div class="card-body">
        <div class="grid-4 gap-4 items-end">
            <div class="form-group mb-0">
                <label class="form-label">Tanggal Awal</label>
                <input type="date" id="start_date" class="form-control" value="{{ $tanggalAwal }}">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Tanggal Akhir</label>
                <input type="date" id="end_date" class="form-control" value="{{ $tanggalAkhir }}">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">&nbsp;</label>
                <button onclick="filterReport()" class="btn btn-sm btn-block" style="background:#e0e7ff; color:#3730a3; border:1px solid #c7d2fe; height: 38px;"><i class="fa-solid fa-filter"></i> Filter Laporan</button>
            </div>
        </div>
    </div>
</div>

<div class="card" id="report-container">
    <div class="card-body text-center hide-on-print" id="report-loading" style="display:none; padding:.75rem;">
        <i class="fa-solid fa-spinner fa-spin text-navy"></i>
        <span id="report-progress" class="text-sm text-muted" style="margin-left:.5rem;">Memuat...</span>
    </div>
    <div class="table-overflow">
        <table id="reportTable">
            <thead>
                <tr>
                    <th>ID Trx</th>
                    <th>Tanggal</th>
                    <th>Nama Pelanggan</th>
                    <th>Metode Pembayaran</th>
                    <th>Status Pesanan</th>
                    <th>Total Pembayaran (Rp)</th>
                </tr>
            </thead>
            <tbody id="reportData">
                <tr><td colspan="6" class="text-center text-muted">Memuat data...</td></tr>
            </tbody>
            <tfoot>
                <tr style="background: var(--color-gray-50);">
                    <td colspan="5" class="text-right font-bold" style="padding: 1rem;">Total Keseluruhan:</td>
                    <td id="grandTotal" class="font-bold text-navy" style="padding: 1rem;">Rp 0</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="report-pagination" class="hide-on-print" style="display:none;padding:.85rem 1rem;border-top:1px solid var(--border-color);align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;">
        <span id="report-page-info" class="text-sm text-muted"></span>
        <div id="report-page-buttons" style="display:flex;gap:.35rem;flex-wrap:wrap;align-items:center;"></div>
    </div>
</div>
@endsection

@push('styles')
<style>
@media print {
    .admin-sidebar, .admin-topbar, .hide-on-print { display: none !important; }
    .admin-main { flex: none; width: 100%; display: block; }
    .admin-content { padding: 0; }
    body { background: #fff; }
    .card { border: none; box-shadow: none; }
    table { width: 100%; border: 1px solid #000; }
    th, td { border: 1px solid #000; padding: 8px; }
    h2 { text-align: center; margin-bottom: 20px; }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => loadReport(1));

let reportDataStore = [];
let currentPage = 1;
let totalPages = 1;
let totalRows = 0;
let loadToken = 0;
const PAGE_LIMIT = 25;
const money = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n);

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

async function loadReport(page = 1) {
    const token = ++loadToken;
    currentPage = Math.max(1, page);

    const start = document.getElementById('start_date').value;
    const end = document.getElementById('end_date').value;
    const loading = document.getElementById('report-loading');
    const progress = document.getElementById('report-progress');
    const tbody = document.getElementById('reportData');
    const pager = document.getElementById('report-pagination');

    loading.style.display = 'block';
    progress.textContent = 'Memuat halaman ' + currentPage + '...';
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Memuat data...</td></tr>';

    try {
        const url = "{{ route('admin.pipeline.laporan') }}"
            + "?tanggal_awal=" + encodeURIComponent(start)
            + "&tanggal_akhir=" + encodeURIComponent(end)
            + "&page=" + currentPage
            + "&limit=" + PAGE_LIMIT;

        const response = await fetch(url);
        const result = await response.json();
        if (token !== loadToken) return;

        if (!result.status) {
            throw new Error(result.message || 'Gagal memuat');
        }

        const rows = result.data || [];
        const meta = result.meta || {};
        totalRows = meta.total_rows || 0;
        totalPages = meta.total_pages || 1;
        currentPage = meta.page || currentPage;
        reportDataStore = rows;

        document.getElementById('grandTotal').innerText = money(meta.grand_total || 0);

        if (totalRows === 0 || rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">Tidak ada data transaksi.</td></tr>';
            pager.style.display = 'none';
            return;
        }

        const frag = document.createDocumentFragment();
        rows.forEach((t) => {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td>#TRX-' + String(t.id_transaction).padStart(5, '0') + '</td>' +
                '<td>' + escapeHtml(t.tanggal) + '</td>' +
                '<td>' + escapeHtml(t.nama) + '</td>' +
                '<td>' + escapeHtml(t.metode_pembayaran) + '</td>' +
                '<td>' + escapeHtml(t.status_pesanan) + '</td>' +
                '<td class="font-bold">' + money(t.total) + '</td>';
            frag.appendChild(tr);
        });
        tbody.innerHTML = '';
        tbody.appendChild(frag);
        renderPagination();
    } catch (e) {
        if (token !== loadToken) return;
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Gagal memuat laporan.</td></tr>';
        pager.style.display = 'none';
        alert('Gagal memuat laporan');
    } finally {
        if (token === loadToken) {
            loading.style.display = 'none';
        }
    }
}

function renderPagination() {
    const pager = document.getElementById('report-pagination');
    const info = document.getElementById('report-page-info');
    const buttons = document.getElementById('report-page-buttons');

    if (totalRows === 0) {
        pager.style.display = 'none';
        return;
    }

    const from = (currentPage - 1) * PAGE_LIMIT + 1;
    const to = Math.min(currentPage * PAGE_LIMIT, totalRows);
    info.textContent = 'Menampilkan ' + from + '–' + to + ' dari ' + totalRows.toLocaleString('id-ID') + ' transaksi';

    buttons.innerHTML = '';
    const addBtn = (label, page, disabled = false, active = false) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = label;
        btn.disabled = disabled;
        btn.style.cssText = 'padding:.3rem .7rem;border-radius:6px;font-size:.82rem;border:1px solid #c7d2fe;cursor:pointer;'
            + (active
                ? 'background:var(--primary);color:#fff;border-color:var(--primary);'
                : 'background:#f0f4ff;color:var(--primary);')
            + (disabled ? 'opacity:.45;cursor:not-allowed;' : '');
        if (!disabled && !active) {
            btn.onclick = () => loadReport(page);
        }
        buttons.appendChild(btn);
    };

    addBtn('‹ Prev', currentPage - 1, currentPage <= 1);

    const windowSize = 2;
    let start = Math.max(1, currentPage - windowSize);
    let end = Math.min(totalPages, currentPage + windowSize);
    if (currentPage <= windowSize) end = Math.min(totalPages, 1 + windowSize * 2);
    if (currentPage > totalPages - windowSize) start = Math.max(1, totalPages - windowSize * 2);

    if (start > 1) {
        addBtn('1', 1, false, currentPage === 1);
        if (start > 2) {
            const dots = document.createElement('span');
            dots.textContent = '…';
            dots.style.cssText = 'padding:0 .25rem;color:#94a3b8;';
            buttons.appendChild(dots);
        }
    }

    for (let p = start; p <= end; p++) {
        addBtn(String(p), p, false, p === currentPage);
    }

    if (end < totalPages) {
        if (end < totalPages - 1) {
            const dots = document.createElement('span');
            dots.textContent = '…';
            dots.style.cssText = 'padding:0 .25rem;color:#94a3b8;';
            buttons.appendChild(dots);
        }
        addBtn(String(totalPages), totalPages, false, currentPage === totalPages);
    }

    addBtn('Next ›', currentPage + 1, currentPage >= totalPages);
    pager.style.display = 'flex';
}

function filterReport() {
    loadReport(1);
}

function exportToExcel() {
    const table = document.getElementById("reportTable");
    const wb = XLSX.utils.table_to_book(table, {sheet: "Laporan Transaksi"});
    XLSX.writeFile(wb, "Laporan_Transaksi_Sinar_Manis.xlsx");
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    doc.setFont("helvetica", "bold");
    doc.text("Laporan Transaksi Toko Sinar Manis", 14, 15);

    const start = document.getElementById('start_date').value || 'Semua Waktu';
    const end = document.getElementById('end_date').value || 'Semua Waktu';

    doc.setFont("helvetica", "normal");
    doc.setFontSize(10);
    doc.text(`Periode: ${start} s/d ${end}`, 14, 22);

    doc.autoTable({
        html: '#reportTable',
        startY: 30,
        theme: 'grid',
        headStyles: { fillColor: [15, 42, 71] },
        footStyles: { fillColor: [245, 247, 250], textColor: [15, 42, 71], fontStyle: 'bold' }
    });

    doc.save('Laporan_Transaksi_Sinar_Manis.pdf');
}
</script>
@endpush
