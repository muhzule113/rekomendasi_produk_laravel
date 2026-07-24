@extends('layouts.admin')

@section('title', 'Upload Data — Toko Sinar Manis')

@section('content')
<div class="page-title-box mb-6">
    <h1 style="font-size:1.75rem;font-weight:800;color:var(--primary);"><i class="fa-solid fa-upload" style="margin-right: 0.5rem; color: var(--color-gold);"></i> Upload Data</h1>
    <p class="text-sm text-muted" style="margin-top:.25rem;">Upload file CSV untuk data transaksi atau produk.</p>
</div>

<!-- Tab Navigation -->
<div style="display:flex;gap:.5rem;margin-bottom:1.5rem;border-bottom:2px solid var(--border-color);padding-bottom:.5rem;">
    <a href="?tab=transaksi" style="text-decoration:none;padding:.5rem 1.25rem;border-radius:8px 8px 0 0;font-weight:600;font-size:.9rem;
        {{ $tab === 'transaksi' ? 'background:var(--primary);color:#fff;' : 'color:var(--muted-foreground);background:transparent;' }}">
        <i class="fa-solid fa-receipt"></i> Upload Transaksi
    </a>
    <a href="?tab=produk" style="text-decoration:none;padding:.5rem 1.25rem;border-radius:8px 8px 0 0;font-weight:600;font-size:.9rem;
        {{ $tab === 'produk' ? 'background:var(--primary);color:#fff;' : 'color:var(--muted-foreground);background:transparent;' }}">
        <i class="fa-solid fa-box"></i> Upload Produk
    </a>
</div>

@if ($tab === 'transaksi')

<div class="card card-body" style="max-width:700px;margin:0 auto;">
    <h2 style="color:var(--primary);margin-bottom:.5rem;">Upload Data Transaksi</h2>
    <p style="color:#888;margin-bottom:1.5rem;">
        Upload file CSV berisi data transaksi mentah.
        Sistem akan otomatis membersihkan, membuat user baru (jika ada email baru), dan mengimport data.
    </p>

    <!-- Download Template -->
    <div style="background:#f0f4ff;border-radius:10px;padding:1rem;margin-bottom:1.5rem;">
        <strong>Gunakan template berikut agar format sesuai:</strong><br>
        <a href="{{ asset('uploads/templates/template_transaksi.csv') }}"
           style="color:var(--primary);margin-right:1rem;" download>Download Template CSV</a>
        <p style="font-size:.8rem;color:#888;margin-top:.5rem;">
            Kolom wajib: <code>tanggal, id_product, qty, harga_satuan</code><br>
            Kolom opsional: <code>email, no_hp, metode_pembayaran, status_pesanan</code>
            &mdash; Jika <code>email</code> diisi dan belum terdaftar, user baru akan dibuat otomatis (password default: <code>pelanggan123</code>).
        </p>
    </div>

    <!-- Area Upload -->
    <div id="drop-area-transaksi" class="drop-area" style="border:2px dashed var(--primary);border-radius:12px;
         padding:2.5rem;text-align:center;cursor:pointer;transition:.2s;"
         onclick="document.getElementById('file-transaksi').click()">
        <div style="font-size:3rem;">&#x1F4C1;</div>
        <p style="color:var(--primary);font-weight:700;margin:.5rem 0;">
            Klik atau drag &amp; drop file di sini</p>
        <p style="color:#888;font-size:.85rem;">Format: CSV &middot; Maks 10MB</p>
        <input type="file" id="file-transaksi" accept=".csv"
               style="display:none;" onchange="handleFileSelectTransaksi(this.files[0])">
    </div>

    <div id="file-info-transaksi" style="display:none;margin-top:1rem;padding:1rem;background:#f8f9fa;border-radius:8px;">
        <strong id="file-name-transaksi"></strong>
        <span id="file-size-transaksi" style="color:#888;font-size:.85rem;margin-left:.5rem;"></span>
    </div>

    <div id="progress-wrap-transaksi" style="display:none;margin-top:1rem;">
        <div style="background:#e9ecef;border-radius:10px;height:12px;overflow:hidden;">
            <div id="progress-bar-transaksi" style="height:100%;background:var(--primary);width:0%;transition:width .3s;border-radius:10px;"></div>
        </div>
        <p id="progress-text-transaksi" style="color:#888;font-size:.85rem;margin-top:.5rem;">Mengupload...</p>
    </div>

    <button id="btn-upload-transaksi" onclick="doUploadTransaksi()" disabled
        style="margin-top:1.5rem;width:100%;padding:.9rem;background:var(--primary);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;">
        Upload &amp; Proses Data
    </button>
</div>

<div class="card card-body" style="margin-top:2rem;">
    <h3 style="color:var(--primary);margin-bottom:1rem;">Riwayat Upload Transaksi</h3>
    <div id="riwayat-container-transaksi">Memuat...</div>
</div>

@else

<div class="card card-body" style="max-width:700px;margin:0 auto;">
    <h2 style="color:var(--primary);margin-bottom:.5rem;">Upload Data Produk</h2>
    <p style="color:#888;margin-bottom:1.5rem;">
        Upload file CSV berisi data produk. Produk dengan nama yang sama akan diupdate (stok, harga, dll),
        produk baru akan ditambahkan.
    </p>

    <div style="background:#f0f4ff;border-radius:10px;padding:1rem;margin-bottom:1.5rem;">
        <strong>Gunakan template berikut agar format sesuai:</strong><br>
        <a href="{{ asset('uploads/templates/template_produk.csv') }}"
           style="color:var(--primary);" download>Download Template CSV</a>
        <p style="font-size:.8rem;color:#888;margin-top:.5rem;">
            Kolom wajib: <code>nama_product, harga, stok</code><br>
            Kolom opsional: <code>nama_category, deskripsi, status, foto</code><br>
            Kolom <code>foto</code>: path relatif di <code>storage/app/public</code>
            (contoh <code>products/nama.jpg</code>). File gambar harus sudah ada di folder itu.<br>
            Kategori baru di CSV akan otomatis dibuat jika belum ada di database.
        </p>
    </div>

    <div id="drop-area-produk" class="drop-area" style="border:2px dashed var(--primary);border-radius:12px;
         padding:2.5rem;text-align:center;cursor:pointer;transition:.2s;"
         onclick="document.getElementById('file-produk').click()">
        <div style="font-size:3rem;">&#x1F4C1;</div>
        <p style="color:var(--primary);font-weight:700;margin:.5rem 0;">
            Klik atau drag &amp; drop file di sini</p>
        <p style="color:#888;font-size:.85rem;">Format: CSV &middot; Maks 10MB</p>
        <input type="file" id="file-produk" accept=".csv"
               style="display:none;" onchange="handleFileSelectProduk(this.files[0])">
    </div>

    <div id="file-info-produk" style="display:none;margin-top:1rem;padding:1rem;background:#f8f9fa;border-radius:8px;">
        <strong id="file-name-produk"></strong>
        <span id="file-size-produk" style="color:#888;font-size:.85rem;margin-left:.5rem;"></span>
    </div>

    <div id="progress-wrap-produk" style="display:none;margin-top:1rem;">
        <div style="background:#e9ecef;border-radius:10px;height:12px;overflow:hidden;">
            <div id="progress-bar-produk" style="height:100%;background:var(--primary);width:0%;transition:width .3s;border-radius:10px;"></div>
        </div>
        <p id="progress-text-produk" style="color:#888;font-size:.85rem;margin-top:.5rem;">Memproses...</p>
    </div>

    <button id="btn-upload-produk" onclick="doUploadProduk()" disabled
        style="margin-top:1.5rem;width:100%;padding:.9rem;background:var(--primary);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;">
        Upload Produk
    </button>
</div>

<div class="card card-body" style="margin-top:2rem;">
    <h3 style="color:var(--primary);margin-bottom:1rem;">Riwayat Upload Produk</h3>
    <div id="riwayat-container-produk">Memuat...</div>
</div>

@endif
@endsection

@push('scripts')
@if ($tab === 'transaksi')
<script>
// ── TRANSACTION UPLOAD ──
let selectedFileTransaksi = null;

function handleFileSelectTransaksi(file) {
    if (!file) return;
    const ext = file.name.split('.').pop().toLowerCase();
    if (ext !== 'csv') { alert('Format harus CSV.'); return; }
    if (file.size > 10 * 1024 * 1024) { alert('Maksimal 10MB.'); return; }
    selectedFileTransaksi = file;
    document.getElementById('file-name-transaksi').textContent = file.name;
    document.getElementById('file-size-transaksi').textContent = '(' + (file.size / 1024).toFixed(1) + ' KB)';
    document.getElementById('file-info-transaksi').style.display = 'block';
    document.getElementById('btn-upload-transaksi').disabled = false;
}

function doUploadTransaksi() {
    if (!selectedFileTransaksi) return;
    const fd = new FormData();
    fd.append('file_transaksi', selectedFileTransaksi);
    fd.append('tab', 'transaksi');
    document.getElementById('progress-wrap-transaksi').style.display = 'block';
    document.getElementById('btn-upload-transaksi').disabled = true;
    const xhr = new XMLHttpRequest();
    xhr.open('POST', "{{ route('admin.upload.store') }}");
    xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.upload.onprogress = function(e) {
        var pct = Math.round(e.loaded / e.total * 100);
        document.getElementById('progress-bar-transaksi').style.width = pct + '%';
        document.getElementById('progress-text-transaksi').textContent = 'Mengupload... ' + pct + '%';
    };
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.ok) {
                document.getElementById('progress-text-transaksi').textContent = 'Upload berhasil! Preprocessing sedang berjalan...';
                pollStatusTransaksi(res.id_upload);
                loadRiwayatTransaksi();
            } else {
                document.getElementById('progress-text-transaksi').textContent = res.pesan;
                document.getElementById('btn-upload-transaksi').disabled = false;
            }
        } catch(e) {
            document.getElementById('progress-text-transaksi').textContent = 'Terjadi kesalahan.';
            document.getElementById('btn-upload-transaksi').disabled = false;
        }
    };
    xhr.onerror = function() {
        document.getElementById('progress-text-transaksi').textContent = 'Gagal upload. Coba lagi.';
        document.getElementById('btn-upload-transaksi').disabled = false;
    };
    xhr.send(fd);
}

function pollStatusTransaksi(idUpload) {
    var attempts = 0;
    var maxAttempts = 60;
    var interval = setInterval(function() {
        attempts++;
        fetch("{{ route('admin.pipeline-status') }}?action=status&id=" + idUpload)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data || !data.id_upload) return;
                var pct = data.total_baris > 0 ? Math.round(data.baris_diimport / data.total_baris * 100) : 0;
                document.getElementById('progress-bar-transaksi').style.width = pct + '%';
                if (data.status === 'menunggu') {
                    document.getElementById('progress-text-transaksi').textContent =
                        'Menunggu pipeline dimulai... (' + attempts + ')';
                } else if (data.status === 'memproses') {
                    document.getElementById('progress-text-transaksi').textContent =
                        'Preprocessing... ' + (data.baris_diimport || 0) + ' / ' + (data.total_baris || '?') + ' baris diimport';
                } else if (data.status === 'gagal') {
                    clearInterval(interval);
                    document.getElementById('progress-text-transaksi').textContent =
                        'Gagal: ' + (data.pesan_error || 'Preprocessing error.');
                    document.getElementById('btn-upload-transaksi').disabled = false;
                    loadRiwayatTransaksi();
                } else if (data.status === 'selesai') {
                    clearInterval(interval);
                    document.getElementById('progress-bar-transaksi').style.width = '100%';
                    document.getElementById('progress-text-transaksi').textContent =
                        'Selesai: ' + (data.baris_diimport || 0) + ' baris diimport, ' +
                        (data.baris_invalid || 0) + ' invalid, ' + (data.baris_duplikat || 0) + ' duplikat';
                    loadRiwayatTransaksi();
                }
            })
            .catch(function() {
                if (attempts >= maxAttempts) {
                    clearInterval(interval);
                    document.getElementById('progress-text-transaksi').textContent =
                        'Gagal memeriksa status. Periksa log pipeline di storage/app/uploads/logs.';
                    document.getElementById('btn-upload-transaksi').disabled = false;
                }
            });
        if (attempts >= maxAttempts) {
            clearInterval(interval);
            document.getElementById('progress-text-transaksi').textContent =
                'Pipeline tidak merespons. Periksa log di storage/app/uploads/logs/pipeline_' + idUpload + '.log';
            document.getElementById('btn-upload-transaksi').disabled = false;
        }
    }, 2000);
}

function loadRiwayatTransaksi() {
    fetch("{{ route('admin.pipeline-status') }}?action=riwayat")
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var el = document.getElementById('riwayat-container-transaksi');
            if (!data.length) { el.innerHTML = '<p style="color:#888;">Belum ada riwayat upload.</p>'; return; }
            var colors = {menunggu:'#888',memproses:'#f39c12',selesai:'#27ae60',gagal:'#e74c3c'};
            var html = '<table style="width:100%;border-collapse:collapse;"><thead><tr style="background:var(--secondary);">';
            html += '<th style="padding:.6rem;text-align:left;">File</th><th style="padding:.6rem;text-align:center;">Total</th>';
            html += '<th style="padding:.6rem;text-align:center;">Imported</th><th style="padding:.6rem;text-align:center;">Invalid</th>';
            html += '<th style="padding:.6rem;text-align:center;">Status</th><th style="padding:.6rem;text-align:left;">Waktu</th><th style="padding:.6rem;text-align:center;">Aksi</th>';
            html += '</tr></thead><tbody>';
            data.forEach(function(r) {
                var c = colors[r.status] || '#888';
                html += '<tr style="border-bottom:1px solid #eee;">';
                html += '<td style="padding:.6rem;">' + r.nama_file_asli + '</td>';
                html += '<td style="padding:.6rem;text-align:center;">' + (r.total_baris||'-') + '</td>';
                html += '<td style="padding:.6rem;text-align:center;color:#27ae60;font-weight:700;">' + (r.baris_diimport||0) + '</td>';
                html += '<td style="padding:.6rem;text-align:center;color:#e74c3c;">' + (r.baris_invalid||0) + '</td>';
                html += '<td style="padding:.6rem;text-align:center;"><span style="background:'+c+';color:#fff;padding:.2rem .7rem;border-radius:20px;font-size:.75rem;">' + r.status + '</span></td>';
                html += '<td style="padding:.6rem;font-size:.82rem;color:#888;">' + r.uploaded_at + '</td>';
                html += '<td style="padding:.6rem;text-align:center;white-space:nowrap;">';
                html += '<a href="{{ route('admin.upload-history') }}?id=' + r.id_upload + '" style="color:var(--primary);font-weight:600;font-size:.82rem;margin-right:.5rem;">Detail</a>';
                html += '<button type="button" onclick="hapusRiwayatUpload(' + r.id_upload + ', \'transaksi\')" style="background:none;border:none;color:#dc2626;font-weight:600;font-size:.82rem;cursor:pointer;">Hapus</button>';
                html += '</td></tr>';
            });
            html += '</tbody></table>';
            el.innerHTML = html;
        });
}

loadRiwayatTransaksi();
</script>
@else
<script>
// ── PRODUCT UPLOAD ──
let selectedFileProduk = null;

function handleFileSelectProduk(file) {
    if (!file) return;
    const ext = file.name.split('.').pop().toLowerCase();
    if (ext !== 'csv') { alert('Format harus CSV.'); return; }
    if (file.size > 10 * 1024 * 1024) { alert('Maksimal 10MB.'); return; }
    selectedFileProduk = file;
    document.getElementById('file-name-produk').textContent = file.name;
    document.getElementById('file-size-produk').textContent = '(' + (file.size / 1024).toFixed(1) + ' KB)';
    document.getElementById('file-info-produk').style.display = 'block';
    document.getElementById('btn-upload-produk').disabled = false;
}

function doUploadProduk() {
    if (!selectedFileProduk) return;
    var fd = new FormData();
    fd.append('file_produk', selectedFileProduk);
    fd.append('tab', 'produk');
    document.getElementById('progress-wrap-produk').style.display = 'block';
    document.getElementById('btn-upload-produk').disabled = true;
    document.getElementById('progress-bar-produk').style.width = '50%';
    document.getElementById('progress-text-produk').textContent = 'Memproses...';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', "{{ route('admin.upload.store') }}?tab=produk");
    xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            document.getElementById('progress-bar-produk').style.width = '100%';
            if (res.ok) {
                document.getElementById('progress-text-produk').textContent = res.pesan;
                if (res.baris_invalid > 0) {
                    document.getElementById('progress-text-produk').textContent += ' (' + res.baris_valid + ' sukses, ' + res.baris_invalid + ' gagal)';
                }
                document.getElementById('btn-upload-produk').disabled = false;
                selectedFileProduk = null;
                document.getElementById('file-info-produk').style.display = 'none';
            } else {
                document.getElementById('progress-text-produk').textContent = res.pesan || 'Gagal memproses.';
                document.getElementById('btn-upload-produk').disabled = false;
            }
            if (res.errors && res.errors.length > 0) {
                var detail = '\nDetail error:\n';
                res.errors.forEach(function(e) { detail += '  Baris ' + e.baris + ': ' + e.pesan + '\n'; });
                alert(detail);
            }
            loadRiwayatProduk();
        } catch(e) {
            document.getElementById('progress-text-produk').textContent = 'Terjadi kesalahan.';
            document.getElementById('btn-upload-produk').disabled = false;
        }
    };
    xhr.onerror = function() {
        document.getElementById('progress-text-produk').textContent = 'Gagal upload. Coba lagi.';
        document.getElementById('btn-upload-produk').disabled = false;
    };
    xhr.send(fd);
}

function loadRiwayatProduk() {
    fetch("{{ route('admin.pipeline-status') }}?action=riwayat&sumber=produk")
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var el = document.getElementById('riwayat-container-produk');
            if (!data.length) { el.innerHTML = '<p style="color:#888;">Belum ada riwayat upload produk.</p>'; return; }
            var colors = {menunggu:'#888',memproses:'#f39c12',selesai:'#27ae60',gagal:'#e74c3c'};
            var html = '<table style="width:100%;border-collapse:collapse;"><thead><tr style="background:var(--secondary);">';
            html += '<th style="padding:.6rem;text-align:left;">File</th><th style="padding:.6rem;text-align:center;">Total</th>';
            html += '<th style="padding:.6rem;text-align:center;">Sukses</th><th style="padding:.6rem;text-align:center;">Gagal</th>';
            html += '<th style="padding:.6rem;text-align:center;">Status</th><th style="padding:.6rem;text-align:left;">Waktu</th><th style="padding:.6rem;text-align:center;">Aksi</th>';
            html += '</tr></thead><tbody>';
            data.forEach(function(r) {
                var c = colors[r.status] || '#888';
                html += '<tr style="border-bottom:1px solid #eee;">';
                html += '<td style="padding:.6rem;">' + r.nama_file_asli + '</td>';
                html += '<td style="padding:.6rem;text-align:center;">' + (r.total_baris||'-') + '</td>';
                html += '<td style="padding:.6rem;text-align:center;color:#27ae60;font-weight:700;">' + (r.baris_valid||0) + '</td>';
                html += '<td style="padding:.6rem;text-align:center;color:#e74c3c;">' + (r.baris_invalid||0) + '</td>';
                html += '<td style="padding:.6rem;text-align:center;"><span style="background:'+c+';color:#fff;padding:.2rem .7rem;border-radius:20px;font-size:.75rem;">' + r.status + '</span></td>';
                html += '<td style="padding:.6rem;font-size:.82rem;color:#888;">' + r.uploaded_at + '</td>';
                html += '<td style="padding:.6rem;text-align:center;white-space:nowrap;">';
                html += '<a href="{{ route('admin.upload-history') }}?id=' + r.id_upload + '" style="color:var(--primary);font-weight:600;font-size:.82rem;margin-right:.5rem;">Detail</a>';
                html += '<button type="button" onclick="hapusRiwayatUpload(' + r.id_upload + ', \'produk\')" style="background:none;border:none;color:#dc2626;font-weight:600;font-size:.82rem;cursor:pointer;">Hapus</button>';
                html += '</td></tr>';
            });
            html += '</tbody></table>';
            el.innerHTML = html;
        });
}

loadRiwayatProduk();
</script>
@endif
<script>
function hapusRiwayatUpload(id, sumber) {
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
            if (sumber === 'produk' && typeof loadRiwayatProduk === 'function') loadRiwayatProduk();
            else if (sumber === 'transaksi' && typeof loadRiwayatTransaksi === 'function') loadRiwayatTransaksi();
            else window.location.href = "{{ route('admin.upload-history') }}";
        } else {
            alert(res.pesan || 'Gagal menghapus riwayat upload.');
        }
    })
    .catch(function() { alert('Gagal menghapus riwayat upload.'); });
}
</script>
@endpush
