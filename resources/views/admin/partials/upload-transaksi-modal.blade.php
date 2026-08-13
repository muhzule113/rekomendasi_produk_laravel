<div class="modal-overlay" id="modalUploadTransaksi" onclick="if (event.target === this) adminModalClose('modalUploadTransaksi')">
    <div class="modal-card" style="max-width:700px;max-height:90vh;overflow-y:auto;">
        <div class="card-body">
            <div class="flex justify-between items-center mb-4">
                <h3 style="margin:0;color:var(--primary);"><i class="fa-solid fa-upload" style="color:var(--color-gold);margin-right:.35rem;"></i> Upload Transaksi</h3>
                <button type="button" class="btn btn-sm btn-outline" onclick="adminModalClose('modalUploadTransaksi')" aria-label="Tutup upload transaksi">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>

            <p class="text-sm text-muted" style="margin-bottom:1rem;">
                Upload CSV atau Excel transaksi untuk diproses oleh pipeline Python.
                <a href="{{ asset('uploads/templates/template_transaksi.csv') }}" download style="color:var(--primary);font-weight:700;">Download template</a>
            </p>

            <div style="background:#f0f4ff;border-radius:10px;padding:1rem;margin-bottom:1rem;font-size:.8rem;color:#64748b;line-height:1.55;">
                Kolom yang disarankan: <code>kode_transaksi, tanggal, id_user, email, id_product, qty, harga_satuan, metode_pembayaran, status_pembayaran, status_pesanan</code>.
            </div>

            <label for="file-upload-transaksi" style="display:block;border:2px dashed var(--primary);border-radius:12px;padding:2rem 1rem;text-align:center;cursor:pointer;transition:.2s;">
                <span style="display:block;font-size:2.5rem;line-height:1;margin-bottom:.5rem;">&#x1F4C1;</span>
                <strong style="display:block;color:var(--primary);">Pilih file transaksi</strong>
                <span style="display:block;color:#888;font-size:.85rem;margin-top:.35rem;">CSV, XLSX, atau XLS &middot; Maksimal 10MB</span>
                <input type="file" id="file-upload-transaksi" accept=".csv,.xlsx,.xls" style="display:none;" onchange="handleFileSelectTransaksiAdmin(this.files[0])">
            </label>

            <div id="file-info-upload-transaksi" style="display:none;margin-top:1rem;padding:.8rem 1rem;background:#f8f9fa;border-radius:8px;">
                <strong id="file-name-upload-transaksi"></strong>
                <span id="file-size-upload-transaksi" style="color:#888;font-size:.85rem;margin-left:.5rem;"></span>
            </div>

            <div id="progress-upload-transaksi" style="display:none;margin-top:1rem;">
                <div style="background:#e9ecef;border-radius:10px;height:10px;overflow:hidden;">
                    <div id="progress-bar-upload-transaksi" style="height:100%;background:var(--primary);width:0%;transition:width .3s;border-radius:10px;"></div>
                </div>
                <p id="progress-text-upload-transaksi" class="text-sm text-muted" style="margin:.5rem 0 0;">Mengupload...</p>
            </div>

            <div class="flex justify-between items-center gap-2 mt-4" style="flex-wrap:wrap;">
                <a href="{{ route('admin.upload-history.transaksi') }}" class="btn btn-sm btn-outline"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Transaksi</a>
                <button type="button" id="btn-upload-transaksi-admin" class="btn btn-sm btn-upload" onclick="doUploadTransaksiAdmin()" disabled>
                    <i class="fa-solid fa-upload"></i> Upload &amp; Proses
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let selectedFileTransaksiAdmin = null;

function setUploadTransaksiStatus(message, color) {
    const status = document.getElementById('progress-text-upload-transaksi');
    status.textContent = message;
    status.style.color = color || '#64748b';
}

function handleFileSelectTransaksiAdmin(file) {
    if (!file) return;

    const extension = file.name.split('.').pop().toLowerCase();
    if (!['csv', 'xlsx', 'xls'].includes(extension)) {
        alert('Format file harus CSV, XLSX, atau XLS.');
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        alert('Ukuran file maksimal 10MB.');
        return;
    }

    selectedFileTransaksiAdmin = file;
    document.getElementById('file-name-upload-transaksi').textContent = file.name;
    document.getElementById('file-size-upload-transaksi').textContent = '(' + (file.size / 1024).toFixed(1) + ' KB)';
    document.getElementById('file-info-upload-transaksi').style.display = 'block';
    document.getElementById('progress-upload-transaksi').style.display = 'none';
    document.getElementById('btn-upload-transaksi-admin').disabled = false;
}

function doUploadTransaksiAdmin() {
    if (!selectedFileTransaksiAdmin) return;

    const formData = new FormData();
    formData.append('file_transaksi', selectedFileTransaksiAdmin);
    const progressWrap = document.getElementById('progress-upload-transaksi');
    const progressBar = document.getElementById('progress-bar-upload-transaksi');
    const uploadButton = document.getElementById('btn-upload-transaksi-admin');

    progressWrap.style.display = 'block';
    progressBar.style.width = '0%';
    setUploadTransaksiStatus('Mengupload...');
    uploadButton.disabled = true;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', "{{ route('admin.transaksi.upload') }}");
    xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.upload.onprogress = function (event) {
        if (!event.lengthComputable) return;
        const percent = Math.round(event.loaded / event.total * 100);
        progressBar.style.width = percent + '%';
        setUploadTransaksiStatus('Mengupload... ' + percent + '%');
    };
    xhr.onload = function () {
        let response;
        try {
            response = JSON.parse(xhr.responseText);
        } catch (error) {
            setUploadTransaksiStatus('Respons server tidak valid.', '#dc2626');
            uploadButton.disabled = false;
            return;
        }

        if (xhr.status >= 200 && xhr.status < 300 && response.ok) {
            setUploadTransaksiStatus('Upload berhasil. Menunggu preprocessing pipeline...');
            pollStatusTransaksiAdmin(response.id_upload);
            return;
        }

        setUploadTransaksiStatus(response.pesan || 'Upload transaksi gagal.', '#dc2626');
        uploadButton.disabled = false;
    };
    xhr.onerror = function () {
        setUploadTransaksiStatus('Gagal terhubung ke server. Coba lagi.', '#dc2626');
        uploadButton.disabled = false;
    };
    xhr.send(formData);
}

function pollStatusTransaksiAdmin(idUpload) {
    let attempts = 0;
    const maxAttempts = 60;
    const interval = setInterval(function () {
        attempts++;
        fetch("{{ route('admin.pipeline-status') }}?action=status&id=" + encodeURIComponent(idUpload))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || !data.id_upload) return;

                const imported = Number(data.baris_diimport || 0);
                const total = Number(data.total_baris || 0);
                const percent = total > 0 ? Math.round(imported / total * 100) : 0;
                document.getElementById('progress-bar-upload-transaksi').style.width = percent + '%';

                if (data.status === 'menunggu') {
                    setUploadTransaksiStatus('Menunggu pipeline dimulai...');
                } else if (data.status === 'memproses') {
                    setUploadTransaksiStatus('Preprocessing... ' + imported + ' / ' + (total || '?') + ' baris diimport');
                } else if (data.status === 'gagal') {
                    clearInterval(interval);
                    setUploadTransaksiStatus('Gagal: ' + (data.pesan_error || 'Preprocessing gagal.'), '#dc2626');
                    document.getElementById('btn-upload-transaksi-admin').disabled = false;
                } else if (data.status === 'selesai') {
                    clearInterval(interval);
                    document.getElementById('progress-bar-upload-transaksi').style.width = '100%';
                    setUploadTransaksiStatus(
                        'Selesai: ' + imported + ' baris diimport, ' + (data.baris_invalid || 0) + ' invalid, ' + (data.baris_duplikat || 0) + ' duplikat.',
                        '#166534'
                    );
                    document.getElementById('btn-upload-transaksi-admin').disabled = false;
                }
            })
            .catch(function () {
                if (attempts >= maxAttempts) {
                    clearInterval(interval);
                    setUploadTransaksiStatus('Gagal memeriksa status pipeline. Buka Riwayat Transaksi untuk detail.', '#dc2626');
                    document.getElementById('btn-upload-transaksi-admin').disabled = false;
                }
            });

        if (attempts >= maxAttempts) {
            clearInterval(interval);
            setUploadTransaksiStatus('Pipeline tidak merespons. Buka Riwayat Transaksi untuk detail.', '#dc2626');
            document.getElementById('btn-upload-transaksi-admin').disabled = false;
        }
    }, 2000);
}
</script>
@endpush
