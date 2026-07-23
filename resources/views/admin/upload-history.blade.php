@extends('layouts.admin')

@section('title', 'Riwayat Upload — Toko Sinar Manis')

@section('content')
<div class="page-title-box mb-6 flex justify-between items-center">
    <div>
        <h1 style="font-size:1.75rem;font-weight:800;color:var(--primary);"><i class="fa-solid fa-clock-rotate-left" style="margin-right: 0.5rem; color: var(--color-gold);"></i> Riwayat Upload Data</h1>
        <p class="text-sm text-muted" style="margin-top:.25rem;">Pantau file yang pernah diupload dan log preprocessing setiap baris data.</p>
    </div>
    <a href="{{ route('admin.upload') }}" class="btn btn-gold btn-sm"><i class="fa-solid fa-plus"></i> Upload Baru</a>
</div>

@if ($id_upload > 0)
    @if (!$detail)
        <div class="card card-body text-center" style="padding:4rem 2rem;">
            <div style="font-size:3rem;margin-bottom:1rem;">&#x1F50D;</div>
            <h3 style="color:var(--primary);margin-bottom:.5rem;">Data Tidak Ditemukan</h3>
            <p style="color:#888;margin-bottom:1.5rem;">Upload dengan ID <strong>#{{ $id_upload }}</strong> tidak tersedia.</p>
            <a href="{{ route('admin.upload-history') }}" class="btn btn-outline btn-sm">Kembali ke Riwayat</a>
        </div>
    @else
        @php
            $imported = $detail->baris_diimport ?? 0;
            $total    = $detail->total_baris ?? 0;
            $pct      = $total > 0 ? round($imported / $total * 100) : 0;
            $isRunning= ($detail->status === 'memproses' || $detail->status === 'menunggu');
        @endphp
        <!-- Detail Upload Header -->
        <div class="card mb-6">
            <div class="card-body">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h3 style="color:var(--primary);margin:0;">Detail Upload #{{ $id_upload }}</h3>
                        <p class="text-sm text-muted" style="margin-top:.25rem;">{{ $detail->nama_file_asli }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @php
                            $badgeColors = ['menunggu'=>'#6b7280','memproses'=>'#f39c12','selesai'=>'#27ae60','gagal'=>'#e74c3c'];
                            $color = $badgeColors[$detail->status] ?? '#888';
                        @endphp
                        <span style="background:{{ $color }};color:#fff;padding:.25rem .85rem;border-radius:20px;font-size:.82rem;font-weight:600;">{{ strtoupper($detail->status) }}</span>
                        @if (!in_array($detail->status, ['menunggu', 'memproses']))
                        <button type="button" onclick="hapusRiwayatUpload({{ $id_upload }})" class="btn btn-outline btn-sm" style="color:#dc2626;border-color:#fecaca;">
                            <i class="fa-solid fa-trash"></i> Hapus
                        </button>
                        @endif
                    </div>
                </div>

                <!-- Progress bar jika memproses -->
                @if ($total > 0)
                <div style="margin-bottom:1rem;">
                    <div class="flex justify-between text-xs text-muted mb-1">
                        <span>{{ $imported }} / {{ $total }} baris diimport</span>
                        <span>{{ $pct }}%</span>
                    </div>
                    <div style="background:#e5e7eb;border-radius:10px;height:10px;overflow:hidden;">
                        <div style="height:100%;width:{{ $pct }}%;background:{{ $isRunning ? '#f39c12' : '#27ae60' }};border-radius:10px;transition:width 1s;"></div>
                    </div>
                </div>
                @endif

                <!-- Grid Info -->
                <div class="grid-4 gap-4 mb-3">
                    <div style="text-align:center;padding:.6rem;background:#f0f4ff;border-radius:8px;">
                        <div style="font-size:.7rem;color:var(--color-muted);">Admin</div>
                        <div style="font-weight:700;color:var(--primary);font-size:.85rem;">{{ $detail->nama_admin ?? '-' }}</div>
                    </div>
                    <div style="text-align:center;padding:.6rem;background:#f0fdf4;border-radius:8px;">
                        <div style="font-size:.7rem;color:var(--color-muted);">Baris Diimport</div>
                        <div style="font-weight:800;color:#059669;font-size:.85rem;">{{ $imported ?: '-' }}</div>
                    </div>
                    <div style="text-align:center;padding:.6rem;background:#fdf2f2;border-radius:8px;">
                        <div style="font-size:.7rem;color:var(--color-muted);">Baris Invalid</div>
                        <div style="font-weight:800;color:#dc2626;font-size:.85rem;">{{ $detail->baris_invalid ?? '0' }}</div>
                    </div>
                    <div style="text-align:center;padding:.6rem;background:#fffbeb;border-radius:8px;">
                        <div style="font-size:.7rem;color:var(--color-muted);">Baris Duplikat</div>
                        <div style="font-weight:800;color:#d97706;font-size:.85rem;">{{ $detail->baris_duplikat ?? '0' }}</div>
                    </div>
                </div>
                <div class="flex gap-4 text-xs text-muted flex-wrap">
                    <span>Upload: {{ !empty($detail->uploaded_at) ? date('d M Y H:i', strtotime($detail->uploaded_at)) : '-' }}</span>
                    <span>|</span>
                    <span>Proses: {{ !empty($detail->processed_at) ? date('d M Y H:i', strtotime($detail->processed_at)) : '-' }}</span>
                    <span>|</span>
                    <span>Tipe: <strong>{{ strtoupper($detail->tipe_file ?? '-') }}</strong></span>
                    <span>|</span>
                    <span>Ukuran: <strong>{{ $detail->ukuran_kb ?? '-' }} KB</strong></span>
                </div>
            </div>
        </div>

        @if (!empty($detail->pesan_error))
        <div class="card mb-6" style="border-left:4px solid #e74c3c;">
            <div class="card-body" style="background:#fdf2f2;">
                <h4 style="color:#dc2626;margin-bottom:.25rem;"><i class="fa-solid fa-triangle-exclamation"></i> Error</h4>
                <p style="color:#991b1b;font-size:.85rem;margin:0;">{!! nl2br(e($detail->pesan_error)) !!}</p>
            </div>
        </div>
        @endif

        <!-- Log Baris -->
        <div class="card">
            <div class="card-body" style="padding-bottom:0;">
                <div class="flex justify-between items-center mb-3">
                    <h3 style="color:var(--primary);margin:0;">Log Per Baris</h3>
                    <div style="display:flex;gap:.35rem;">
                        @php
                            $filters = ['all' => 'Semua', 'imported' => 'Imported', 'invalid' => 'Invalid', 'duplikat' => 'Duplikat'];
                        @endphp
                        @foreach ($filters as $fk => $fv)
                            @php $isActive = $filter === $fk; @endphp
                            <a href="?id={{ $id_upload }}&filter={{ $fk }}"
                               style="padding:.3rem .75rem;border-radius:20px;font-size:.75rem;text-decoration:none;font-weight:600;
                                      {{ $isActive ? 'background:var(--primary);color:#fff;' : 'background:#f0f4ff;color:var(--primary);' }}">
                                {{ $fv }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="table-overflow">
                <table style="width:100%;font-size:.84rem;border-top:1px solid var(--border-color);">
                    <thead>
                        <tr>
                            <th style="width:8%;">#Baris</th>
                            <th style="width:12%;">Status</th>
                            <th style="width:12%;">ID Transaksi</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (empty($logs))
                            <tr><td colspan="4" class="text-center py-5 text-muted">Tidak ada log untuk filter ini.</td></tr>
                        @else
                            @foreach ($logs as $log)
                                @php
                                    $rowColors = ['imported'=>'#ecfdf5','invalid'=>'#fef2f2','duplikat'=>'#fffbeb','skip'=>'#f8f9fa'];
                                    $rowColor  = $rowColors[$log->status_baris] ?? '#f8f9fa';
                                    $stColors  = ['imported'=>'#059669','invalid'=>'#dc2626','duplikat'=>'#d97706','skip'=>'#6b7280'];
                                    $stColor   = $stColors[$log->status_baris] ?? '#6b7280';
                                @endphp
                            <tr style="background:{{ $rowColor }};">
                                <td class="text-center">{{ $log->nomor_baris }}</td>
                                <td>
                                    <span style="display:inline-block;background:{{ $stColor }};color:#fff;padding:.15rem .55rem;border-radius:10px;font-size:.7rem;font-weight:600;">{{ $log->status_baris }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($log->id_transaction)
                                        <a href="{{ route('admin.transaksi') }}" style="color:var(--primary);font-weight:600;">#{{ $log->id_transaction }}</a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td style="font-size:.8rem;">{{ $log->keterangan ?? '' }}</td>
                            </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <div style="margin-top:1.5rem;">
            <a href="{{ route('admin.upload-history') }}" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Kembali ke Riwayat</a>
        </div>

        @if ($isRunning)
        <script>
        (function() {
            var checkInterval = setInterval(function() {
                fetch("{{ route('admin.pipeline-status') }}?action=status&id={{ $id_upload }}")
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data && data.status && !['menunggu','memproses'].includes(data.status)) {
                            clearInterval(checkInterval);
                            window.location.reload();
                        }
                    });
            }, 3000);
        })();
        </script>
        @endif
    @endif
@else
    <!-- Daftar Riwayat Semua Upload -->
    <div class="card">
        <div class="card-body" style="padding-bottom:0;">
            <div class="flex justify-between items-center mb-3">
                <h3 style="color:var(--primary);margin:0;">Daftar Semua Upload</h3>
                @if ($count_all > 0)
                <span class="text-xs text-muted">Total {{ number_format($count_all) }} upload</span>
                @endif
            </div>
        </div>
        <div class="table-overflow">
            <table style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:6%;">ID</th>
                        <th>Nama File</th>
                        <th>Admin</th>
                        <th style="width:7%;">Tipe</th>
                        <th class="text-center" style="width:8%;">Total</th>
                        <th class="text-center" style="width:9%;">Diimport</th>
                        <th class="text-center" style="width:8%;">Invalid</th>
                        <th style="width:10%;">Status</th>
                        <th style="width:12%;">Waktu</th>
                        <th style="width:7%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @if (empty($riwayat))
                        <tr><td colspan="10" class="text-center py-6 text-muted">
                            <div style="font-size:2rem;margin-bottom:.5rem;">&#x1F4C2;</div>
                            Belum ada riwayat upload.<br>
                            <a href="{{ route('admin.upload') }}" style="color:var(--primary);font-weight:600;">Upload data pertama</a>
                        </td></tr>
                    @else
                        @foreach ($riwayat as $r)
                            @php
                                $badgeColors = ['menunggu'=>'#6b7280','memproses'=>'#f39c12','selesai'=>'#27ae60','gagal'=>'#e74c3c'];
                                $bc = $badgeColors[$r->status] ?? '#888';
                            @endphp
                        <tr>
                            <td class="text-center">#{{ $r->id_upload }}</td>
                            <td><strong>{{ $r->nama_file_asli }}</strong></td>
                            <td class="text-sm">{{ $r->nama_admin }}</td>
                            <td><span style="display:inline-block;background:#f0f4ff;color:var(--primary);padding:.1rem .5rem;border-radius:8px;font-size:.72rem;font-weight:600;">{{ strtoupper($r->tipe_file) }}</span></td>
                            <td class="text-center">{{ $r->total_baris ?? '-' }}</td>
                            <td class="text-center font-bold" style="color:#059669;">{{ $r->baris_diimport ?? 0 }}</td>
                            <td class="text-center" style="color:#dc2626;">{{ $r->baris_invalid ?? 0 }}</td>
                            <td>
                                <span style="display:inline-block;background:{{ $bc }};color:#fff;padding:.2rem .65rem;border-radius:12px;font-size:.72rem;font-weight:600;">{{ $r->status }}</span>
                            </td>
                            <td class="text-xs text-muted">{{ !empty($r->uploaded_at) ? date('d M, H:i', strtotime($r->uploaded_at)) : '-' }}</td>
                            <td style="white-space:nowrap;">
                                <a href="?id={{ $r->id_upload }}" class="btn btn-outline btn-sm" style="padding:.25rem .6rem;font-size:.75rem;">Detail</a>
                                @if (!in_array($r->status, ['menunggu', 'memproses']))
                                <button type="button" onclick="hapusRiwayatUpload({{ $r->id_upload }})" class="btn btn-outline btn-sm" style="padding:.25rem .6rem;font-size:.75rem;color:#dc2626;border-color:#fecaca;margin-left:.25rem;">Hapus</button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
        @if ($total_pages_list > 1)
        <div style="padding:.75rem 1rem;border-top:1px solid var(--border-color);display:flex;justify-content:center;gap:.4rem;">
            @for ($p = 1; $p <= $total_pages_list; $p++)
                <a href="?page_list={{ $p }}" style="padding:.3rem .7rem;border-radius:6px;font-size:.82rem;text-decoration:none;
                    {{ $p === $page_list ? 'background:var(--primary);color:#fff;' : 'background:#f0f4ff;color:var(--primary);' }}">
                    {{ $p }}
                </a>
            @endfor
        </div>
        @endif
    </div>
@endif
@endsection

@push('scripts')
<script>
function hapusRiwayatUpload(id) {
    if (!confirm('Hapus riwayat upload #' + id + '? File dan log terkait akan dihapus permanen.')) return;
    fetch("{{ url('admin/upload-history') }}/" + id, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) {
            window.location.href = "{{ route('admin.upload-history') }}";
        } else {
            alert(res.pesan || 'Gagal menghapus riwayat upload.');
        }
    })
    .catch(function() { alert('Gagal menghapus riwayat upload.'); });
}
</script>
@endpush
