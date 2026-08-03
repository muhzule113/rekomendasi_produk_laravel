@extends('layouts.admin')

@section('title', 'Dashboard — Toko Sinar Manis')

@push('styles')
<style>
/* CSS khusus untuk desain modern meniru Applify */
.applify-card {
    background: var(--color-white);
    border: none;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(15, 42, 71, 0.04);
    transition: all 0.3s ease;
}
.applify-card:hover {
    box-shadow: 0 8px 30px rgba(15, 42, 71, 0.08);
    transform: translateY(-2px);
}
.applify-stat-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--color-muted);
    margin-bottom: 0.75rem;
}
.applify-stat-value {
    font-size: 1.8rem;
    font-family: var(--font-heading);
    color: var(--color-navy);
    font-weight: 800;
    line-height: 1;
}
.circular-progress {
    position: relative;
    width: 60px;
    height: 60px;
}
.circular-progress svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}
.circular-progress .bg {
    fill: none;
    stroke: var(--color-gray-100);
    stroke-width: 3;
}
.circular-progress .progress {
    fill: none;
    stroke-width: 3;
    stroke-linecap: round;
    animation: dash 1.5s ease-out forwards;
}
.circular-progress .text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 0.7rem;
    font-weight: 700;
}
@keyframes dash {
    from { stroke-dasharray: 0, 100; }
}

.job-posted-card {
    border-radius: 12px;
    padding: 1.2rem 1.25rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}
</style>
@endpush

@section('content')
<div class="page-title-box mb-6">
    <h1 style="font-size: 1.75rem; font-weight: 800;">Dashboard</h1>
</div>

<div class="grid-cols-2-1">
    <!-- KOLOM KIRI (UTAMA) -->
    <div class="flex flex-col gap-6">

        <!-- ROW STATS -->
        <div class="grid-2 gap-6">
            <div class="applify-card flex justify-between items-center">
                <div>
                    <div class="applify-stat-title">Total Produk</div>
                    <div class="applify-stat-value">{{ number_format($totalProducts) }}</div>
                    <div style="font-size: 0.75rem; color: {{ $dashboardTrends['products']['color'] }}; font-weight: 600; margin-top: 8px;"><i class="fa-solid {{ $dashboardTrends['products']['icon'] }}"></i> {{ $dashboardTrends['products']['display'] }} {{ $dashboardTrends['products']['label'] }}</div>
                </div>
                <div class="circular-progress">
                    <svg viewBox="0 0 36 36">
                        <path class="bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="progress" stroke="{{ $dashboardTrends['products']['color'] }}" stroke-dasharray="{{ $dashboardTrends['products']['progress'] }}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="text" style="color: {{ $dashboardTrends['products']['color'] }}">{{ $dashboardTrends['products']['circle_display'] }}</div>
                </div>
            </div>

            <div class="applify-card flex justify-between items-center">
                <div>
                    <div class="applify-stat-title">Total Pelanggan</div>
                    <div class="applify-stat-value">{{ number_format($totalCustomers) }}</div>
                    <div style="font-size: 0.75rem; color: {{ $dashboardTrends['customers']['color'] }}; font-weight: 600; margin-top: 8px;"><i class="fa-solid {{ $dashboardTrends['customers']['icon'] }}"></i> {{ $dashboardTrends['customers']['display'] }} {{ $dashboardTrends['customers']['label'] }}</div>
                </div>
                <div class="circular-progress">
                    <svg viewBox="0 0 36 36">
                        <path class="bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="progress" stroke="{{ $dashboardTrends['customers']['color'] }}" stroke-dasharray="{{ $dashboardTrends['customers']['progress'] }}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="text" style="color: {{ $dashboardTrends['customers']['color'] }}">{{ $dashboardTrends['customers']['circle_display'] }}</div>
                </div>
            </div>

            <div class="applify-card flex justify-between items-center">
                <div>
                    <div class="applify-stat-title">Total Transaksi</div>
                    <div class="applify-stat-value">{{ number_format($totalTransactions) }}</div>
                    <div style="font-size: 0.75rem; color: #10b981; font-weight: 600; margin-top: 8px;"><i class="fa-solid fa-arrow-trend-up"></i> +24% Inc</div>
                </div>
                <div class="circular-progress">
                    <svg viewBox="0 0 36 36">
                        <path class="bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="progress" stroke="#10b981" stroke-dasharray="75, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="text" style="color: #10b981">75%</div>
                </div>
            </div>

            <div class="applify-card flex justify-between items-center">
                <div>
                    <div class="applify-stat-title">Pendapatan</div>
                    <div class="applify-stat-value" style="font-size: 1.3rem; padding-top: 4px;">{{ \App\Helpers\Helpers::formatRupiah($totalRevenue ?? 0) }}</div>
                    <div style="font-size: 0.75rem; color: {{ $dashboardTrends['revenue']['color'] }}; font-weight: 600; margin-top: 8px;"><i class="fa-solid {{ $dashboardTrends['revenue']['icon'] }}"></i> {{ $dashboardTrends['revenue']['display'] }} {{ $dashboardTrends['revenue']['label'] }}</div>
                </div>
                <div class="circular-progress">
                    <svg viewBox="0 0 36 36">
                        <path class="bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="progress" stroke="{{ $dashboardTrends['revenue']['color'] }}" stroke-dasharray="{{ $dashboardTrends['revenue']['progress'] }}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="text" style="color: {{ $dashboardTrends['revenue']['color'] }}">{{ $dashboardTrends['revenue']['circle_display'] }}</div>
                </div>
            </div>
        </div>

        <!-- ROW CHARTS -->
        <div class="applify-card">
            <div class="flex justify-between items-center mb-4">
                <h3 style="margin:0; font-size: 1.1rem; color: var(--color-navy);">Statistik Transaksi Bulanan</h3>
                <div class="badge badge-gray">6 Bulan Terakhir</div>
            </div>
            <div style="position: relative; height: 300px;">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <div class="applify-card">
            <div class="flex justify-between items-center mb-4">
                <h3 style="margin:0; font-size: 1.1rem; color: var(--color-navy);">Kategori Paling Diminati</h3>
                <div class="badge badge-gray">Bulan Ini</div>
            </div>
            <div style="position: relative; height: 300px;">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>

    </div>

    <!-- KOLOM KANAN (SIDEBAR-LIKE) -->
    <div class="flex flex-col gap-6">

        <!-- Profile Info -->
        <div class="text-center mb-2 flex flex-col items-center">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--color-navy); color: white; display: grid; place-items: center; font-size: 2rem; font-weight: bold; margin-bottom: 1rem; border: 4px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                {{ strtoupper(substr($user->nama ?? 'A', 0, 1)) }}
            </div>
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--color-navy);">{{ $user->nama ?? 'Admin' }}</h3>
            <p style="margin: 0; font-size: 0.8rem; color: var(--color-muted);">Administrator Sinar Manis</p>
        </div>

        <div class="flex items-center justify-between">
            <h4 style="margin: 0; font-size: 1rem; color: var(--color-navy);">Ringkasan Toko</h4>
        </div>

        <!-- Job Posted like cards -->
        <div class="job-posted-card" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);">
            <div>
                <div style="font-size: 1.5rem; font-weight: 800;">{{ number_format($totalProducts) }}</div>
                <div style="font-size: 0.85rem; font-weight: 600;">Produk Aktif</div>
                <div style="font-size: 0.7rem; opacity: 0.8;">Total di etalase</div>
            </div>
            <div style="font-size: 2rem; opacity: 0.5;"><i class="fa-solid fa-box"></i></div>
        </div>

        <div class="job-posted-card" style="background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%);">
            <div>
                <div style="font-size: 1.5rem; font-weight: 800;">{{ number_format($totalCustomers) }}</div>
                <div style="font-size: 0.85rem; font-weight: 600;">Pelanggan</div>
                <div style="font-size: 0.7rem; opacity: 0.8;">Total terdaftar</div>
            </div>
            <div style="font-size: 2rem; opacity: 0.5;"><i class="fa-solid fa-users"></i></div>
        </div>

        <!-- Reminders like section -->
        <div class="mt-2">
            <div class="flex justify-between items-center mb-4">
                <h4 style="margin: 0; font-size: 1rem; color: var(--color-navy);">Transaksi Terbaru</h4>
                <a href="{{ route('admin.transaksi') }}" style="font-size: 0.75rem; color: var(--color-navy); font-weight: 600;">Lihat Semua</a>
            </div>

            <div class="flex flex-col gap-3">
                @if (empty($recentTransactions))
                    <div class="text-center text-muted" style="font-size: 0.85rem; padding: 1rem;">Belum ada transaksi.</div>
                @else
                    @foreach ($recentTransactions as $rt)
                        <div class="flex items-center justify-between" style="background: white; padding: 1rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(15,42,71,0.03);">
                            <div class="flex items-center gap-3">
                                <div style="width: 36px; height: 36px; border-radius: 8px; background: var(--color-gray-50); display: grid; place-items: center; color: var(--color-navy);">
                                    <i class="fa-solid fa-receipt"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; font-size: 0.85rem; color: var(--color-navy);">{{ $rt->nama }}</div>
                                    <div style="font-size: 0.7rem; color: var(--color-muted); margin-top: 2px;">{{ date('d M Y, H:i', strtotime($rt->created_at)) }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div style="font-weight: 700; font-size: 0.85rem; color: var(--color-navy);">{{ \App\Helpers\Helpers::formatRupiah($rt->total) }}</div>
                                @php
                                    $statusClass = 'gray';
                                    if ($rt->status_pembayaran === 'Dibayar') $statusClass = 'green';
                                    elseif ($rt->status_pembayaran === 'Belum Dibayar') $statusClass = 'red';
                                @endphp
                                <span class="badge badge-{{ $statusClass }}" style="font-size: 0.6rem; padding: 0.1rem 0.4rem; margin-top: 2px; display: inline-block;">{{ $rt->status_pembayaran }}</span>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly chart
    var monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    var gradient = monthlyCtx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(15, 42, 71, 0.25)');
    gradient.addColorStop(1, 'rgba(15, 42, 71, 0.0)');

    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode($monthlyTransactions->pluck('month_name')) !!},
            datasets: [{
                label: 'Jumlah Transaksi',
                data: {!! json_encode($monthlyTransactions->pluck('count')) !!},
                borderColor: '#0f2a47',
                borderWidth: 3,
                backgroundColor: gradient,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#0f2a47',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 10, cornerRadius: 8, displayColors: false
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#64748b' } },
                y: { grid: { color: '#f1f5f9', borderDash: [5,5] }, ticks: { color: '#64748b', stepSize: 1 } }
            },
            interaction: { intersect: false, mode: 'index' }
        }
    });

    // Category chart
    var catCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(catCtx, {
        type: 'doughnut',
        data: {
            labels: {!! json_encode($kategoriPopuler->pluck('nama_category')) !!},
            datasets: [{
                data: {!! json_encode($kategoriPopuler->pluck('total_qty')) !!},
                backgroundColor: ['#0F2A47','#D4A84B','#0ea5e9','#10b981','#f43f5e','#8b5cf6','#ec4899','#f97316'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } },
                tooltip: { backgroundColor: '#0f172a', padding: 10, cornerRadius: 8 }
            }
        }
    });
});
</script>
@endpush
