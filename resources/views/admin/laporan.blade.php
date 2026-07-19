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
                <input type="date" id="start_date" class="form-control">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Tanggal Akhir</label>
                <input type="date" id="end_date" class="form-control">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">&nbsp;</label>
                <button onclick="loadReport()" class="btn btn-sm btn-block" style="background:#e0e7ff; color:#3730a3; border:1px solid #c7d2fe; height: 38px;"><i class="fa-solid fa-filter"></i> Filter Laporan</button>
            </div>
        </div>
    </div>
</div>

<div class="card" id="report-container">
    <div class="card-body text-center" id="report-loading" style="display:none;">
        <i class="fa-solid fa-spinner fa-spin fa-2x text-navy"></i>
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
                <!-- Data loaded via JS -->
            </tbody>
            <tfoot>
                <tr style="background: var(--color-gray-50);">
                    <td colspan="5" class="text-right font-bold" style="padding: 1rem;">Total Keseluruhan:</td>
                    <td id="grandTotal" class="font-bold text-navy" style="padding: 1rem;">Rp 0</td>
                </tr>
            </tfoot>
        </table>
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
document.addEventListener('DOMContentLoaded', loadReport);

let reportDataStore = [];

async function loadReport() {
    const start = document.getElementById('start_date').value;
    const end = document.getElementById('end_date').value;
    const loading = document.getElementById('report-loading');
    const tbody = document.getElementById('reportData');

    loading.style.display = 'block';
    tbody.innerHTML = '';

    try {
        const response = await fetch("{{ route('admin.pipeline.laporan') }}?tanggal_awal=" + start + "&tanggal_akhir=" + end);
        const result = await response.json();

        loading.style.display = 'none';

        if (result.status) {
            reportDataStore = result.data;
            let grandTotal = 0;

            if (result.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">Tidak ada data transaksi.</td></tr>';
                document.getElementById('grandTotal').innerText = 'Rp 0';
                return;
            }

            result.data.forEach(t => {
                grandTotal += parseFloat(t.total);
                tbody.innerHTML += `
                <tr>
                    <td>#TRX-${t.id_transaction.toString().padStart(5, '0')}</td>
                    <td>${t.tanggal}</td>
                    <td>${t.nama}</td>
                    <td>${t.metode_pembayaran}</td>
                    <td>${t.status_pesanan}</td>
                    <td class="font-bold">${new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(t.total)}</td>
                </tr>`;
            });

            document.getElementById('grandTotal').innerText = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(grandTotal);
        }
    } catch(e) {
        loading.style.display = 'none';
        alert('Gagal memuat laporan');
    }
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
