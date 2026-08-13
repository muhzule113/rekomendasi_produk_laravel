<div class="modal-overlay" id="modalUploadProduk" onclick="if (event.target === this) adminModalClose('modalUploadProduk')">
    <div class="modal-card" style="max-width:700px;max-height:90vh;overflow-y:auto;">
        <div class="card-body">
            <div class="flex justify-between items-center mb-4">
                <h3 style="margin:0;color:var(--primary);"><i class="fa-solid fa-upload" style="color:var(--color-gold);margin-right:.35rem;"></i> Upload Produk</h3>
                <button type="button" class="btn btn-sm btn-outline" onclick="adminModalClose('modalUploadProduk')" aria-label="Tutup upload produk">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>

            <p class="text-sm text-muted" style="margin-bottom:1rem;">
                Upload CSV untuk menambahkan produk baru atau memperbarui produk dengan nama yang sama.
                <a href="{{ asset('uploads/templates/template_produk.csv') }}" download style="color:var(--primary);font-weight:700;">Download template</a>
            </p>

            <div style="background:#f0f4ff;border-radius:10px;padding:1rem;margin-bottom:1rem;font-size:.8rem;color:#64748b;line-height:1.55;">
                Kolom wajib: <code>nama_product, harga, stok</code>. Kolom opsional: <code>nama_category, deskripsi, status, foto</code>.
            </div>

            <label for="file-upload-produk" style="display:block;border:2px dashed var(--primary);border-radius:12px;padding:2rem 1rem;text-align:center;cursor:pointer;transition:.2s;">
                <span style="display:block;font-size:2.5rem;line-height:1;margin-bottom:.5rem;">&#x1F4C1;</span>
                <strong style="display:block;color:var(--primary);">Pilih file produk</strong>
                <span style="display:block;color:#888;font-size:.85rem;margin-top:.35rem;">CSV &middot; Maksimal 10MB</span>
                <input type="file" id="file-upload-produk" accept=".csv" style="display:none;" onchange="handleFileSelectProdukAdmin(this.files[0])">
            </label>

            <div id="file-info-upload-produk" style="display:none;margin-top:1rem;padding:.8rem 1rem;background:#f8f9fa;border-radius:8px;">
                <strong id="file-name-upload-produk"></strong>
                <span id="file-size-upload-produk" style="color:#888;font-size:.85rem;margin-left:.5rem;"></span>
            </div>

            <p id="status-upload-produk" class="text-sm" style="display:none;margin:1rem 0 0;"></p>

            <div class="flex justify-between items-center gap-2 mt-4" style="flex-wrap:wrap;">
                <a href="{{ route('admin.upload-history.produk') }}" class="btn btn-sm btn-outline"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Produk</a>
                <button type="button" id="btn-upload-produk-admin" class="btn btn-sm btn-upload" onclick="doUploadProdukAdmin()" disabled>
                    <i class="fa-solid fa-upload"></i> Upload Produk
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let selectedFileProdukAdmin = null;

function setUploadProdukStatus(message, color) {
    const status = document.getElementById('status-upload-produk');
    status.textContent = message;
    status.style.color = color || '#64748b';
    status.style.display = 'block';
}

function handleFileSelectProdukAdmin(file) {
    if (!file) return;

    const extension = file.name.split('.').pop().toLowerCase();
    if (extension !== 'csv') {
        alert('Format file harus CSV.');
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        alert('Ukuran file maksimal 10MB.');
        return;
    }

    selectedFileProdukAdmin = file;
    document.getElementById('file-name-upload-produk').textContent = file.name;
    document.getElementById('file-size-upload-produk').textContent = '(' + (file.size / 1024).toFixed(1) + ' KB)';
    document.getElementById('file-info-upload-produk').style.display = 'block';
    document.getElementById('status-upload-produk').style.display = 'none';
    document.getElementById('btn-upload-produk-admin').disabled = false;
}

function doUploadProdukAdmin() {
    if (!selectedFileProdukAdmin) return;

    const formData = new FormData();
    formData.append('file_produk', selectedFileProdukAdmin);
    const uploadButton = document.getElementById('btn-upload-produk-admin');

    setUploadProdukStatus('Memproses CSV produk...');
    uploadButton.disabled = true;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', "{{ route('admin.produk.upload') }}");
    xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.onload = function () {
        let response;
        try {
            response = JSON.parse(xhr.responseText);
        } catch (error) {
            setUploadProdukStatus('Respons server tidak valid.', '#dc2626');
            uploadButton.disabled = false;
            return;
        }

        if (xhr.status >= 200 && xhr.status < 300 && response.ok) {
            let message = response.pesan || 'Upload produk selesai.';
            if (response.baris_invalid > 0) {
                message += ' ' + response.baris_invalid + ' baris gagal.';
            }
            setUploadProdukStatus(message, '#166534');

            if (response.errors && response.errors.length > 0) {
                const detail = response.errors.map(function (item) {
                    return 'Baris ' + item.baris + ': ' + item.pesan;
                }).join('\n');
                alert('Detail baris yang gagal:\n' + detail);
            }

            setTimeout(function () { window.location.reload(); }, 1200);
            return;
        }

        setUploadProdukStatus(response.pesan || 'Upload produk gagal.', '#dc2626');
        uploadButton.disabled = false;
    };
    xhr.onerror = function () {
        setUploadProdukStatus('Gagal terhubung ke server. Coba lagi.', '#dc2626');
        uploadButton.disabled = false;
    };
    xhr.send(formData);
}
</script>
@endpush
