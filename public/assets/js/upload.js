let selectedFile = null;

// Drag & drop support
const dropArea = document.getElementById('drop-area');
['dragenter','dragover'].forEach(e =>
  dropArea.addEventListener(e, ev => {
    ev.preventDefault();
    dropArea.style.background = '#f0f4ff';
  })
);
['dragleave','drop'].forEach(e =>
  dropArea.addEventListener(e, ev => {
    ev.preventDefault();
    dropArea.style.background = '';
    if (ev.type === 'drop') handleFileSelect(ev.dataTransfer.files[0]);
  })
);

function handleFileSelect(file) {
  if (!file) return;
  const ext = file.name.split('.').pop().toLowerCase();
  if (!['csv','xlsx','xls'].includes(ext)) {
    alert('Format tidak didukung. Gunakan CSV atau Excel.');
    return;
  }
  if (file.size > 10 * 1024 * 1024) {
    alert('Ukuran file terlalu besar. Maksimal 10MB.');
    return;
  }
  selectedFile = file;
  document.getElementById('file-name').textContent = file.name;
  document.getElementById('file-size').textContent =
    `(${(file.size / 1024).toFixed(1)} KB)`;
  document.getElementById('file-info').style.display = 'block';
  document.getElementById('btn-upload').disabled = false;
}

function doUpload() {
  if (!selectedFile) return;

  const formData = new FormData();
  formData.append('file_transaksi', selectedFile);

  document.getElementById('progress-wrap').style.display = 'block';
  document.getElementById('btn-upload').disabled = true;

  const xhr = new XMLHttpRequest();
  xhr.open('POST', '../api/upload.php');

  xhr.upload.onprogress = e => {
    const pct = Math.round(e.loaded / e.total * 100);
    document.getElementById('progress-bar').style.width = pct + '%';
    document.getElementById('progress-text').textContent = `Mengupload... ${pct}%`;
  };

  xhr.onload = () => {
    try {
      const res = JSON.parse(xhr.responseText);
      if (res.ok) {
        document.getElementById('progress-text').textContent =
          'Upload berhasil! Preprocessing sedang berjalan...';
        pollStatus(res.id_upload);
        loadRiwayat();
      } else {
        document.getElementById('progress-text').textContent = res.pesan;
        document.getElementById('btn-upload').disabled = false;
      }
    } catch (e) {
      document.getElementById('progress-text').textContent = 'Terjadi kesalahan.';
      document.getElementById('btn-upload').disabled = false;
    }
  };

  xhr.onerror = () => {
    document.getElementById('progress-text').textContent = 'Gagal upload. Coba lagi.';
    document.getElementById('btn-upload').disabled = false;
  };

  xhr.send(formData);
}

function pollStatus(idUpload) {
  const interval = setInterval(() => {
    fetch(`../api/pipeline-status.php?action=status&id=${idUpload}`)
      .then(r => r.json())
      .then(data => {
        if (!data || !data.id_upload) return;

        const pct = data.total_baris > 0
          ? Math.round(data.baris_diimport / data.total_baris * 100)
          : 0;

        document.getElementById('progress-bar').style.width = pct + '%';
        document.getElementById('progress-text').textContent =
          `Preprocessing... ${data.baris_diimport || 0} / ${data.total_baris || '?'} baris diimport`;

        if (['selesai','gagal'].includes(data.status)) {
          clearInterval(interval);
          const icon = data.status === 'selesai' ? '' : '';
          document.getElementById('progress-text').textContent =
            `${icon} Selesai: ${data.baris_diimport} baris diimport, ` +
            `${data.baris_invalid} invalid, ${data.baris_duplikat} duplikat`;
          loadRiwayat();
        }
      });
  }, 2000);
}

function loadRiwayat() {
  fetch('../api/pipeline-status.php?action=riwayat')
    .then(r => r.json())
    .then(data => {
      const el = document.getElementById('riwayat-container');
      if (!data.length) { el.innerHTML = '<p style="color:#888;">Belum ada riwayat upload.</p>'; return; }

      const badge = s => {
        const colors = {menunggu:'#888',memproses:'#f39c12',selesai:'#27ae60',gagal:'#e74c3c'};
        return `<span style="background:${colors[s]||'#888'};color:#fff;padding:.2rem .7rem;border-radius:20px;font-size:.75rem;">${s}</span>`;
      };

      el.innerHTML = `<table style="width:100%;border-collapse:collapse;">
        <thead><tr style="background:var(--secondary);">
          <th style="padding:.6rem;text-align:left;">File</th>
          <th style="padding:.6rem;text-align:center;">Total</th>
          <th style="padding:.6rem;text-align:center;">Imported</th>
          <th style="padding:.6rem;text-align:center;">Invalid</th>
          <th style="padding:.6rem;text-align:center;">Status</th>
          <th style="padding:.6rem;text-align:left;">Waktu</th>
        </tr></thead>
        <tbody>${data.map(r => `
          <tr style="border-bottom:1px solid #eee;">
            <td style="padding:.6rem;">${r.nama_file_asli}</td>
            <td style="padding:.6rem;text-align:center;">${r.total_baris||'-'}</td>
            <td style="padding:.6rem;text-align:center;color:#27ae60;font-weight:700;">${r.baris_diimport||0}</td>
            <td style="padding:.6rem;text-align:center;color:#e74c3c;">${r.baris_invalid||0}</td>
            <td style="padding:.6rem;text-align:center;">${badge(r.status)}</td>
            <td style="padding:.6rem;font-size:.82rem;color:#888;">${r.uploaded_at}</td>
          </tr>`).join('')}
        </tbody></table>`;
    });
}

// Load riwayat saat halaman terbuka
loadRiwayat();
