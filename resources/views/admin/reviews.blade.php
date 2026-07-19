@extends('layouts.admin')

@section('title', 'Ulasan & Rating — Toko Sinar Manis')

@php
function star_icons(int $rating): string {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $rating
            ? '<i class="fa-solid fa-star" style="color:#f59e0b;font-size:.75rem;"></i>'
            : '<i class="fa-regular fa-star" style="color:#d1d5db;font-size:.75rem;"></i>';
    }
    return $html;
}
@endphp

@section('content')
<div class="page-title-box mb-6 flex justify-between items-center">
    <div>
        <h1 style="font-size:1.75rem;font-weight:800;color:var(--primary);"><i class="fa-solid fa-star" style="margin-right: 0.5rem; color: var(--color-gold);"></i> Ulasan &amp; Rating Produk</h1>
        <p class="text-sm text-muted" style="margin-top:.25rem;">Pantau ulasan pelanggan, distribusi rating, dan produk dengan rating tertinggi.</p>
    </div>
</div>

<!-- ====== Stat Cards ====== -->
<div class="mt-6 grid-4 gap-6 mb-8">
    <div class="stat-card">
        <div class="stat-icon navy"><i class="fa-solid fa-comment-dots"></i></div>
        <div class="stat-details">
            <h4>Total Ulasan</h4>
            <h2>{{ number_format($total_reviews) }}</h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fa-solid fa-star"></i></div>
        <div class="stat-details">
            <h4>Rata-rata Rating</h4>
            <h2>{{ $avg_rating }} <span style="font-size:.7rem;color:var(--color-muted);">/ 5</span></h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-users"></i></div>
        <div class="stat-details">
            <h4>Pelanggan Mereview</h4>
            <h2>{{ number_format($total_reviewers) }}</h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fa-solid fa-box"></i></div>
        <div class="stat-details">
            <h4>Produk Direview</h4>
            <h2>{{ number_format($total_rated_prods) }}</h2>
        </div>
    </div>
</div>

<!-- ====== Chart + Top Rated ====== -->
<div class="grid-2 gap-6 mb-8 mt-8">
    <div class="card">
        <div class="card-body">
            <h3 class="mb-4">Distribusi Rating</h3>
            <div style="position:relative;height:260px;">
                <canvas id="ratingDistChart"></canvas>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="padding-bottom:0;">
            <h3 class="mb-4">Top 10 Produk Rating Tertinggi</h3>
            <div class="table-overflow">
                <table style="font-size:.85rem;">
                    <thead>
                        <tr>
                            <th style="width:5%;">#</th>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th class="text-center">Ulasan</th>
                            <th>Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (empty($top_rated))
                            <tr><td colspan="5" class="text-center py-4 text-muted">Belum cukup data (minimal 2 ulasan per produk).</td></tr>
                        @else
                            @php $no = 1; @endphp
                            @foreach ($top_rated as $tp)
                            <tr>
                                <td class="text-center text-muted">{{ $no++ }}</td>
                                <td class="font-bold">{{ $tp->nama_product }}</td>
                                <td><span style="background:#f0f4ff;color:var(--primary);padding:.1rem .5rem;border-radius:10px;font-size:.7rem;">{{ $tp->nama_category }}</span></td>
                                <td class="text-center">{{ $tp->review_count }}x</td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        {!! star_icons(round($tp->avg_rating)) !!}
                                        <span class="font-bold" style="margin-left:.35rem;">{{ $tp->avg_rating }}</span>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ====== Filter + Daftar Ulasan ====== -->
<div class="card mb-8 mt-8">
    <div class="card-body" style="padding-bottom:0;">
        <div class="flex justify-between items-center mb-3 flex-wrap gap-3">
            <h3 style="color:var(--primary);margin:0;">Daftar Ulasan</h3>
            <div class="flex items-center gap-3 flex-wrap">
                <form method="GET" style="display:flex;gap:.5rem;align-items:center;">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Cari produk atau pelanggan..." class="form-control" style="padding:.35rem .7rem;font-size:.82rem;width:200px;">
                    <select name="rating" class="form-control" style="padding:.35rem .7rem;font-size:.82rem;width:auto;" onchange="this.form.submit()">
                        <option value="0" {{ $rating_filter === 0 ? 'selected' : '' }}>Semua Rating</option>
                        @for ($i = 5; $i >= 1; $i--)
                        <option value="{{ $i }}" {{ $rating_filter === $i ? 'selected' : '' }}>{{ $i }} Bintang</option>
                        @endfor
                    </select>
                    <button type="submit" class="btn btn-outline btn-sm" style="padding:.3rem .6rem;">Cari</button>
                    @if ($search || $rating_filter)
                    <a href="{{ route('admin.reviews') }}" class="btn btn-sm" style="color:var(--color-muted);text-decoration:none;">Reset</a>
                    @endif
                </form>
            </div>
        </div>
    </div>
    <div class="table-overflow">
        <table style="font-size:.85rem;border-top:1px solid var(--border-color);">
            <thead>
                <tr>
                    <th style="width:12%;">Pelanggan</th>
                    <th>Produk</th>
                    <th style="width:8%;">Kategori</th>
                    <th style="width:10%;">Rating</th>
                    <th>Komentar</th>
                    <th style="width:12%;">Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @if (empty($reviews))
                    <tr><td colspan="6" class="text-center py-6 text-muted">
                        <div style="font-size:2rem;margin-bottom:.5rem;">&#x2B50;</div>
                        {{ $search ? "Tidak ada ulasan untuk pencarian ini." : "Belum ada ulasan produk." }}
                    </td></tr>
                @else
                    @foreach ($reviews as $r)
                    <tr>
                        <td class="font-bold">{{ $r->reviewer }}</td>
                        <td>
                            <a href="{{ route('produk.detail', $r->id_product) }}" target="_blank" style="color:var(--primary);text-decoration:none;font-weight:600;">{{ $r->nama_product }}</a>
                        </td>
                        <td><span style="background:#f0f4ff;color:var(--primary);padding:.1rem .5rem;border-radius:10px;font-size:.7rem;">{{ $r->nama_category }}</span></td>
                        <td>{!! star_icons($r->rating) !!}</td>
                        <td style="font-size:.82rem;color:#555;">{{ $r->komentar ?? '-' }}</td>
                        <td style="font-size:.78rem;color:#888;">{{ date('d M Y, H:i', strtotime($r->created_at)) }}</td>
                    </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
    @if ($total_pages > 1)
        {!! \App\Helpers\Helpers::renderPagination($page, $total_pages, request()->except('page')) !!}
    @endif
</div>
@endsection

@push('scripts')
<script>
(function() {
    var ctx = document.getElementById('ratingDistChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {!! $chartLabels !!},
            datasets: [{
                label: 'Jumlah Ulasan',
                data: {!! $chartData !!},
                backgroundColor: {!! $chartColors !!},
                borderRadius: 6,
                borderWidth: 0,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f3f4f6' } },
                y: { grid: { display: false } }
            }
        }
    });
})();
</script>
@endpush
