@extends('layouts.admin')

@section('title', 'Analisis Rekomendasi — Toko Sinar Manis')

@section('content')
<div class="page-title-box mb-6 flex justify-between items-center">
    <div>
        <h1 style="font-size:1.75rem;font-weight:800;color:var(--primary);"><i class="fa-solid fa-diagram-project" style="margin-right: 0.5rem; color: var(--color-gold);"></i> Analisis Rekomendasi</h1>
        <p class="text-sm text-muted" style="margin-top:.25rem;">Item-Based CF — Cosine Similarity pada matriks interaksi biner (transaksi Selesai + Dibayar).</p>
    </div>
    <div class="flex items-center gap-3">
        @if ($last_updated)
        <span class="text-xs text-muted">Update: {{ date('d M H:i', strtotime($last_updated)) }}</span>
        @endif
        <button onclick="hitungSimilarity()" class="btn btn-upload btn-sm" id="btnProses">
            <i class="fa-solid fa-rotate" id="btnProsesIcon"></i> <span id="btnProsesText">Hitung Ulang</span>
        </button>
    </div>
</div>

@if (!empty($recommendation_dirty))
<div class="alert-card mb-6" style="background:#fff7ed;border:1px solid #fdba74;border-radius:12px;padding:1rem 1.25rem;">
    <div style="display:flex;gap:.75rem;align-items:flex-start;">
        <i class="fa-solid fa-triangle-exclamation" style="color:#c2410c;margin-top:.15rem;"></i>
        <div>
            <div style="font-weight:700;color:#9a3412;">Model rekomendasi perlu dihitung ulang</div>
            <div class="text-sm" style="color:#9a3412;margin-top:.25rem;">
                Terdapat transaksi valid baru sejak kalkulasi terakhir. Klik <strong>Hitung Ulang</strong> agar skor cosine diperbarui.
                Ambang minimum co-occurrence saat ini: <strong>{{ $min_co_occurrence ?? 2 }}</strong>.
            </div>
        </div>
    </div>
</div>
@endif

<!-- ====== Stat Cards ====== -->
<div class="mt-6 grid-4 gap-6 mb-8">
    <div class="stat-card">
        <div class="stat-icon navy"><i class="fa-solid fa-box"></i></div>
        <div class="stat-details">
            <h4>Produk Aktif</h4>
            <h2>{{ number_format($total_produk) }}</h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-receipt"></i></div>
        <div class="stat-details">
            <h4>Transaksi Valid</h4>
            <h2>{{ number_format($total_trans) }}</h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fa-solid fa-link"></i></div>
        <div class="stat-details">
            <h4>Pasangan Similarity</h4>
            <h2>{{ number_format($total_pairs) }}</h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fa-solid fa-bullhorn"></i></div>
        <div class="stat-details">
            <h4>Total Rekomendasi</h4>
            <h2>{{ number_format($total_rekomendasi) }}</h2>
        </div>
    </div>
</div>

<!-- ====== Matrix Summary ====== -->
<div class="grid-3 gap-6 mb-8 mt-6">
    <div class="card" style="grid-column: span 2;">
        <div class="card-body">
            <h3 class="mb-4">Ringkasan Matriks Similarity</h3>
            <div class="grid-4 gap-4 mb-4">
                <div style="text-align:center; padding:.75rem; background:#f0f4ff; border-radius:10px;">
                    <div style="font-size:.72rem; color: var(--color-muted); margin-bottom:.25rem;">Produk Masuk Matriks</div>
                    <div style="font-size:1.4rem; font-weight:800; color:var(--primary);">{{ $matrix_products }}</div>
                </div>
                <div style="text-align:center; padding:.75rem; background:#f0fdf4; border-radius:10px;">
                    <div style="font-size:.72rem; color: var(--color-muted); margin-bottom:.25rem;">Pair Coverage</div>
                    <div style="font-size:1.4rem; font-weight:800; color:#059669;">{{ $pair_coverage ?? $coverage }}%</div>
                    <div style="font-size:.65rem;color:var(--color-muted);margin-top:.15rem;">% pasangan produk berkemiripan</div>
                </div>
                <div style="text-align:center; padding:.75rem; background:#fffbeb; border-radius:10px;">
                    <div style="font-size:.72rem; color: var(--color-muted); margin-bottom:.25rem;">Avg Skor</div>
                    <div style="font-size:1.4rem; font-weight:800; color:#d97706;">{{ number_format($avg_score, 3) }}</div>
                </div>
                <div style="text-align:center; padding:.75rem; background:#fdf2f2; border-radius:10px;">
                    <div style="font-size:.72rem; color: var(--color-muted); margin-bottom:.25rem;">Std Deviasi</div>
                    <div style="font-size:1.4rem; font-weight:800; color:#dc2626;">{{ number_format($std_score, 4) }}</div>
                </div>
            </div>
            <div class="grid-3 gap-4">
                <div style="display:flex;justify-content:space-between;padding:.5rem .75rem;background:#f8f9fa;border-radius:8px;font-size:.85rem;">
                    <span class="text-muted">Skor Tertinggi</span>
                    <span class="font-bold text-green">{{ number_format($max_score, 4) }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:.5rem .75rem;background:#f8f9fa;border-radius:8px;font-size:.85rem;">
                    <span class="text-muted">Skor Terendah</span>
                    <span class="font-bold text-red">{{ number_format($min_score, 4) }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:.5rem .75rem;background:#f8f9fa;border-radius:8px;font-size:.85rem;">
                    <span class="text-muted">Pasangan Ideal</span>
                    <span class="font-bold">{{ number_format($ideal_pairs) }}</span>
                </div>
            </div>
            @if (!empty($source_stats))
            <div style="margin-top:1rem;display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;">
                <span class="text-xs text-muted">Sumber:</span>
                @foreach ($source_stats as $src)
                <span style="background:#eff6ff;color:var(--primary);padding:.15rem .65rem;border-radius:12px;font-size:.72rem;font-weight:600;">
                    {{ $src->source }}: {{ number_format($src->cnt) }} (avg {{ number_format($src->avg_score, 3) }})
                </span>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h3 class="mb-4">Distribusi Skor</h3>
            <div style="position:relative;height:260px;">
                <canvas id="scoreDistChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ====== Evaluasi Akademik ====== -->
<div class="card mb-8 mt-6">
    <div class="card-body">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h3 style="margin:0;">Evaluasi Akademik (Time-based Holdout)</h3>
                <p class="text-xs text-muted" style="margin:.35rem 0 0;">Similarity dibangun ulang hanya dari data latih. Catalog Coverage@K ≠ Pair Coverage.</p>
            </div>
        </div>
        <div class="table-overflow">
            <table style="font-size:.85rem;">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Metode</th>
                        <th class="text-center">K</th>
                        <th class="text-center">Users</th>
                        <th class="text-center">Precision</th>
                        <th class="text-center">Recall</th>
                        <th class="text-center">F1</th>
                        <th class="text-center">Hit Rate</th>
                        <th class="text-center">Catalog Cov@K</th>
                    </tr>
                </thead>
                <tbody>
                    @if (empty($evaluation_logs) || count($evaluation_logs) === 0)
                        <tr><td colspan="9" class="text-center py-4 text-muted">Belum ada hasil evaluasi. Jalankan pipeline/batch CF atau evaluator Python.</td></tr>
                    @else
                        @foreach ($evaluation_logs as $ev)
                        <tr>
                            <td>{{ date('d M Y H:i', strtotime($ev->evaluated_at)) }}</td>
                            <td><span style="font-size:.72rem;">{{ $ev->method }}</span></td>
                            <td class="text-center font-bold">{{ $ev->k_value }}</td>
                            <td class="text-center">{{ $ev->users_evaluated }}</td>
                            <td class="text-center">{{ number_format($ev->precision_at_k, 4) }}</td>
                            <td class="text-center">{{ number_format($ev->recall_at_k, 4) }}</td>
                            <td class="text-center">{{ number_format($ev->f1_at_k, 4) }}</td>
                            <td class="text-center">{{ number_format($ev->hit_rate_at_k, 4) }}</td>
                            <td class="text-center">{{ number_format($ev->catalog_coverage_at_k, 2) }}%</td>
                        </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ====== Top Produk + CF Run History ====== -->
<div class="grid-2 gap-6 mb-8 mt-6">
    <div class="card">
        <div class="card-body" style="padding-bottom:0;">
            <h3 class="mb-4">Top 10 Produk Paling Direkomendasikan</h3>
            <div class="table-overflow">
                <table style="font-size:.85rem;">
                    <thead>
                        <tr>
                            <th style="width:5%;">#</th>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Avg Skor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (empty($top_recommended))
                            <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada data rekomendasi. Jalankan perhitungan similarity terlebih dahulu.</td></tr>
                        @else
                            @php $no = 1; @endphp
                            @foreach ($top_recommended as $tp)
                            <tr>
                                <td class="text-center text-muted">{{ $no++ }}</td>
                                <td class="font-bold">{{ $tp->nama_product }}</td>
                                <td><span style="background:#f0f4ff;color:var(--primary);padding:.1rem .5rem;border-radius:10px;font-size:.7rem;">{{ $tp->nama_category }}</span></td>
                                <td class="text-center font-bold text-green">{{ $tp->total_rek }}x</td>
                                <td class="text-center">{{ number_format($tp->avg_score_rek, 3) }}</td>
                            </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="padding-bottom:0;">
            <div class="flex justify-between items-center mb-4">
                <h3 style="margin:0;">Riwayat CF Engine</h3>
                @if (!empty($cf_runs))
                <span class="text-xs text-muted">{{ count($cf_runs) }} run terakhir</span>
                @endif
            </div>
            <div class="table-overflow">
                <table style="font-size:.82rem;">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th class="text-center">Pairs</th>
                            <th class="text-center">Durasi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (empty($cf_runs))
                            <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada histori CF run.</td></tr>
                        @else
                            @foreach ($cf_runs as $run)
                            <tr>
                                <td>{{ date('d M Y, H:i', strtotime($run->started_at)) }}</td>
                                <td class="text-center font-bold">{{ number_format($run->total_pairs ?? 0) }}</td>
                                <td class="text-center">{{ $run->duration_seconds ?? '-' }} detik</td>
                                <td>
                                    @php
                                        $stColors = ['running'=>'#f39c12','success'=>'#27ae60','failed'=>'#e74c3c'];
                                        $c = $stColors[$run->status] ?? '#888';
                                    @endphp
                                    <span style="background:{{ $c }};color:#fff;padding:.15rem .6rem;border-radius:12px;font-size:.7rem;">{{ $run->status }}</span>
                                </td>
                            </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
            <div style="border-top:1px solid var(--border-color); margin-top:.75rem; padding-top:.75rem;">
                <h3 class="mb-3" style="font-size:.9rem;">Log Rekomendasi Harian</h3>
                <div class="table-overflow">
                    <table style="font-size:.82rem;">
                        <thead>
                            <tr><th>Tanggal</th><th class="text-center">Jumlah</th><th class="text-center">Avg Skor</th></tr>
                        </thead>
                        <tbody>
                            @if (empty($rek_log_summary))
                                <tr><td colspan="3" class="text-center py-4 text-muted">Belum ada log.</td></tr>
                            @else
                                @foreach ($rek_log_summary as $rls)
                                <tr>
                                    <td>{{ date('d M Y', strtotime($rls->tgl)) }}</td>
                                    <td class="text-center font-bold text-green">{{ number_format($rls->jumlah) }}</td>
                                    <td class="text-center">{{ number_format($rls->avg_score, 4) }}</td>
                                </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====== Similarity Pairs Table ====== -->
<div class="card mb-8 mt-6">
    <div class="card-body" style="padding-bottom:0;">
        <div class="flex justify-between items-center mb-4">
            <h3 style="margin:0;">Daftar Pasangan Kemiripan Produk</h3>
            @if ($count_pairs > 0)
            <span class="text-xs text-muted">Menampilkan {{ min($limit_sim, $count_pairs) }} dari {{ number_format($count_pairs) }} pasangan</span>
            @endif
        </div>
    </div>
    <div class="table-overflow">
        <table style="font-size: 0.88rem; border-top: 1px solid var(--border-color);">
            <thead>
                <tr>
                    <th style="width:5%;">#</th>
                    <th>Produk A</th>
                    <th>Produk B</th>
                    <th class="text-center">Co-Occ</th>
                    <th>Sumber</th>
                    <th style="width:25%;">Skor Kemiripan</th>
                </tr>
            </thead>
            <tbody>
                @if (empty($similarities))
                    <tr><td colspan="6" class="text-center py-6 text-muted">Belum ada data. Klik "Hitung Ulang" untuk menjalankan perhitungan similarity.</td></tr>
                @else
                    @php $no = $offset_sim + 1; @endphp
                    @foreach ($similarities as $s)
                    <tr>
                        <td class="text-center text-muted">{{ $no++ }}</td>
                        <td class="font-bold">
                            <a href="{{ route('produk.detail', $s->p1_id) }}" target="_blank" style="color:var(--primary);text-decoration:none;">{{ $s->p1_name }}</a>
                        </td>
                        <td class="font-bold">
                            <a href="{{ route('produk.detail', $s->p2_id) }}" target="_blank" style="color:var(--primary);text-decoration:none;">{{ $s->p2_name }}</a>
                        </td>
                        <td class="text-center">{{ $s->co_occurrence }}</td>
                        <td><span style="background:#eff6ff;color:#2563eb;padding:.1rem .5rem;border-radius:10px;font-size:.7rem;">{{ $s->source }}</span></td>
                        <td>
                            <div class="flex items-center gap-2">
                                <div style="flex:1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                    <div style="height: 100%; width: {{ min($s->score * 100, 100) }}%;
                                        background: {{ $s->score >= 0.4 ? '#d97706' : '#2563eb' }}; border-radius:4px;"></div>
                                </div>
                                <span class="font-bold" style="min-width:38px;font-size:.82rem;">{{ number_format($s->score, 2) }}</span>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
    {!! \App\Helpers\Helpers::renderPagination($currentPage, $totalPages, request()->query()) !!}
</div>

<!-- Modal Hasil Perhitungan -->
<div id="modalResult" class="modal-overlay" onclick="adminModalClose('modalResult')">
    <div class="modal-card" style="max-width: 450px; border-radius: 16px; overflow: hidden; padding: 0;" onclick="event.stopPropagation()">
        <div style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 1.75rem 1.5rem 1.5rem; position: relative; text-align: center;">
            <button onclick="adminModalClose('modalResult')" title="Tutup" style="position: absolute; top: 0.75rem; right: 0.75rem; background: rgba(255,255,255,0.15); border: none; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; color: #fff; font-size: 0.85rem; display: flex; align-items: center; justify-content: center; transition: all .2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)';this.style.transform='rotate(90deg)'" onmouseout="this.style.background='rgba(255,255,255,0.15)';this.style.transform='none'">
                <i class="fa-solid fa-times"></i>
            </button>
            <div style="width: 56px; height: 56px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem;">
                <i class="fa-solid fa-circle-check" style="color: #fff; font-size: 1.75rem;"></i>
            </div>
            <h3 style="color: #fff; font-size: 1.1rem; font-weight: 700; margin: 0 0 0.25rem;">Perhitungan Selesai!</h3>
            <p id="resultMessage" style="color: rgba(255,255,255,0.85); font-size: 0.82rem; margin: 0; line-height: 1.4;"></p>
        </div>
        <div style="padding: 1.25rem 1.5rem;">
            <p style="font-size: 0.78rem; font-weight: 700; letter-spacing: 0.06em; color: var(--color-muted); text-transform: uppercase; margin-bottom: 0.875rem;">Ringkasan</p>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.55rem 0.75rem; background: #f0fdf4; border-radius: 10px; border: 1px solid #bbf7d0;">
                    <i class="fa-solid fa-users" style="color: #059669; font-size: 0.8rem; width:20px;text-align:center;"></i>
                    <div style="flex:1;display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:.72rem;color:var(--color-muted);">Total Users</span>
                        <span id="resUsers" style="font-weight:800;color:#065f46;"></span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.55rem 0.75rem; background: #eff6ff; border-radius: 10px; border: 1px solid #bfdbfe;">
                    <i class="fa-solid fa-boxes-stacked" style="color: #2563eb; font-size: 0.8rem; width:20px;text-align:center;"></i>
                    <div style="flex:1;display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:.72rem;color:var(--color-muted);">Produk Masuk Matriks</span>
                        <span id="resProducts" style="font-weight:800;color:#1e3a8a;"></span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.55rem 0.75rem; background: #f0fdf4; border-radius: 10px; border: 1px solid #bbf7d0;">
                    <i class="fa-solid fa-link" style="color: #059669; font-size: 0.8rem; width:20px;text-align:center;"></i>
                    <div style="flex:1;display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:.72rem;color:var(--color-muted);">Pasangan Tersimpan</span>
                        <span id="resSavedPairs" style="font-weight:800;color:#065f46;"></span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.55rem 0.75rem; background: #fffbeb; border-radius: 10px; border: 1px solid #fde68a;">
                    <i class="fa-solid fa-chart-pie" style="color: #d97706; font-size: 0.8rem; width:20px;text-align:center;"></i>
                    <div style="flex:1;display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:.72rem;color:var(--color-muted);">Coverage</span>
                        <span id="resCoverage" style="font-weight:800;color:#92400e;"></span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.55rem 0.75rem; background: #f8f9fa; border-radius: 10px; border: 1px solid #e5e7eb;">
                    <i class="fa-solid fa-stopwatch" style="color: #6366f1; font-size: 0.8rem; width:20px;text-align:center;"></i>
                    <div style="flex:1;display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:.72rem;color:var(--color-muted);">Waktu Eksekusi</span>
                        <span id="resExecutionTime" style="font-weight:800;color:#4338ca;"></span>
                    </div>
                </div>
            </div>
        </div>
        <div style="padding: 0 1.5rem 1.5rem;">
            <button class="btn btn-upload" onclick="window.location.reload()" style="width: 100%; justify-content: center; padding: 0.75rem; font-size: 0.95rem; font-weight: 700; border-radius: 10px;">
                <i class="fa-solid fa-rotate"></i> Selesai &amp; Refresh
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    var ctx = document.getElementById('scoreDistChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {!! $chartLabels !!},
            datasets: [{
                label: 'Jumlah Pasangan',
                data: {!! $chartData !!},
                backgroundColor: ['#dbeafe','#93c5fd','#fcd34d','#f59e0b','#d97706'],
                borderRadius: 6,
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f3f4f6' } },
                x: { grid: { display: false } }
            }
        }
    });
})();

async function hitungSimilarity() {
    var btn = document.getElementById('btnProses');
    var icon = document.getElementById('btnProsesIcon');
    var text = document.getElementById('btnProsesText');
    btn.disabled = true;
    btn.style.opacity = '0.7';
    btn.style.cursor = 'not-allowed';
    icon.className = 'fa-solid fa-spinner fa-spin';
    text.textContent = 'Memproses...';

    try {
        var res = await fetch('{{ route('admin.similarity.recalculate') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ csrf_token: '{{ csrf_token() }}' })
        });
        var result = await res.json();

        if (result.status) {
            document.getElementById('resultMessage').innerText = result.message;
            document.getElementById('resSavedPairs').innerText = result.summary.saved_pairs;
            document.getElementById('resCoverage').innerText = result.summary.coverage_percentage + '%';
            document.getElementById('resExecutionTime').innerText = result.summary.execution_time_seconds + ' detik';
            document.getElementById('resUsers').innerText = result.summary.total_users ?? '-';
            document.getElementById('resProducts').innerText = result.summary.total_products_in_matrix ?? '-';
            adminModalOpen('modalResult');
        } else {
            alert('Gagal: ' + result.message);
        }
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        icon.className = 'fa-solid fa-rotate';
        text.textContent = 'Hitung Ulang';
    } catch (e) {
        alert('Terjadi kesalahan. Periksa Console (F12).');
        console.error(e);
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        icon.className = 'fa-solid fa-rotate';
        text.textContent = 'Hitung Ulang';
    }
}
</script>
@endpush
