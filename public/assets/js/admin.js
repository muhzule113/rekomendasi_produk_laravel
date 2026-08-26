// admin/js/admin.js - Central admin JavaScript

/* ─── Sidebar toggle ────────────────────────────────────────── */
function toggleSidebar() {
    document.body.classList.toggle('sidebar-open');
}

function closeSidebar() {
    document.body.classList.remove('sidebar-open');
}

/* ─── Inject persistent UI elements ─────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    injectConfirmDialog();
    injectLoadingOverlay();
    injectToastContainer();

    // Chart dashboard/analisis diinisialisasi inline di Blade dengan data Laravel.
    // Jangan fetch endpoint .php lama (tidak tersedia).

    // Auto-dismiss success alerts after 4 seconds
    document.querySelectorAll('.alert-card').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-8px)';
            setTimeout(() => el.remove(), 400);
        }, 4000);
    });
});

function injectConfirmDialog() {
    if (document.getElementById('admin-confirm-overlay')) return;
    document.body.insertAdjacentHTML('beforeend', `
        <div id="admin-confirm-overlay">
            <div id="admin-confirm-box">
                <div class="confirm-body">
                    <div class="confirm-icon-wrap danger" id="confirm-icon-wrap">
                        <div class="icon-circle"><i id="confirm-icon" class="fa-solid fa-trash-can"></i></div>
                    </div>
                    <p class="confirm-title" id="confirm-title">Konfirmasi Aksi</p>
                    <p class="confirm-desc"  id="confirm-desc">Apakah Anda yakin ingin melanjutkan?</p>
                </div>
                <div class="confirm-actions">
                    <button class="btn-cancel" onclick="adminConfirmClose()"><i class="fa-solid fa-xmark"></i> Batal</button>
                    <button class="btn-danger-solid" id="confirm-ok-btn" onclick="adminConfirmExecute()"><i class="fa-solid fa-check"></i> Ya, Lanjutkan</button>
                </div>
            </div>
        </div>
    `);
}

function injectLoadingOverlay() {
    if (document.getElementById('admin-loading-overlay')) return;
    document.body.insertAdjacentHTML('beforeend', `
        <div id="admin-loading-overlay">
            <div class="admin-spinner"></div>
            <div class="admin-loading-text" id="admin-loading-text">Memproses...</div>
        </div>
    `);
}

function injectToastContainer() {
    if (document.getElementById('admin-toast-container')) return;
    document.body.insertAdjacentHTML('beforeend', `<div id="admin-toast-container"></div>`);
}

/* ─── Confirm Dialog API ─────────────────────────────────────── */
let _confirmCallback = null;

/**
 * Show a custom confirm dialog.
 * @param {object} opts - { title, desc, type: 'danger'|'warning'|'info', icon, okLabel, onConfirm }
 */
function adminConfirm(opts) {
    const type    = opts.type    || 'danger';
    const icon    = opts.icon    || (type === 'danger' ? 'fa-trash-can' : type === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-info');
    const okClass = type === 'danger' ? 'btn-danger-solid' : type === 'warning' ? 'btn-warning-solid' : 'btn-primary-solid';
    const okLabel = opts.okLabel || 'Ya, Lanjutkan';

    document.getElementById('confirm-title').textContent    = opts.title || 'Konfirmasi Aksi';
    renderConfirmDescription(opts.desc || 'Apakah Anda yakin?');
    document.getElementById('confirm-icon').className       = `fa-solid ${icon}`;
    document.getElementById('confirm-icon-wrap').className  = `confirm-icon-wrap ${type}`;

    const okBtn = document.getElementById('confirm-ok-btn');
    okBtn.className   = okClass;
    okBtn.innerHTML   = `<i class="fa-solid fa-check"></i> ${okLabel}`;

    _confirmCallback = opts.onConfirm || null;

    const overlay = document.getElementById('admin-confirm-overlay');
    overlay.classList.add('show');
    document.addEventListener('keydown', _confirmKeyHandler);
}

function renderConfirmDescription(desc) {
    const element = document.getElementById('confirm-desc');
    element.replaceChildren();

    if (!desc || typeof desc !== 'object') {
        element.textContent = desc || 'Apakah Anda yakin?';
        return;
    }

    element.append(document.createTextNode(desc.before || ''));

    if (desc.emphasis) {
        const emphasis = document.createElement('strong');
        emphasis.textContent = desc.emphasis;
        element.append(emphasis);
    }

    element.append(document.createTextNode(desc.after || ''));

    if (desc.note) {
        element.append(document.createElement('br'));
        element.append(document.createTextNode(desc.note));
    }
}

function adminConfirmClose() {
    document.getElementById('admin-confirm-overlay').classList.remove('show');
    document.removeEventListener('keydown', _confirmKeyHandler);
    _confirmCallback = null;
}

function adminConfirmExecute() {
    const cb = _confirmCallback;
    adminConfirmClose();
    if (typeof cb === 'function') cb();
}

function _confirmKeyHandler(e) {
    if (e.key === 'Escape') adminConfirmClose();
}

// Close on backdrop click
document.addEventListener('click', (e) => {
    if (e.target.id === 'admin-confirm-overlay') adminConfirmClose();
});

/* ─── Loading Overlay API ────────────────────────────────────── */
function adminShowLoading(text = 'Memproses...') {
    document.getElementById('admin-loading-text').textContent = text;
    document.getElementById('admin-loading-overlay').classList.add('show');
}

function adminHideLoading() {
    document.getElementById('admin-loading-overlay').classList.remove('show');
}

/* ─── Toast API ──────────────────────────────────────────────── */
function adminToast(message, type = 'info') {
    const container = document.getElementById('admin-toast-container');
    const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info' };
    const icon  = icons[type] || icons.info;

    const toast = document.createElement('div');
    toast.className = `admin-toast ${type}`;
    toast.innerHTML = `<i class="fa-solid ${icon}"></i> <span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'admin-toast-out 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

/* ─── Delete confirmation helpers ───────────────────────────── */
function confirmDeleteProduct(form) {
    adminConfirm({
        type: 'danger',
        title: 'Hapus Produk?',
        desc: 'Produk yang dihapus tidak dapat dikembalikan. Lanjutkan?',
        okLabel: 'Hapus Produk',
        onConfirm: () => {
            adminShowLoading('Menghapus produk...');
            form.submit();
        }
    });
}

function bulkToggleAll(masterId, name) {
    const checked = document.getElementById(masterId).checked;
    document.querySelectorAll(`input.bulk-check[name="${name}"]`).forEach(el => { el.checked = checked; });
    bulkUpdateBar(name);
}

function bulkUpdateBar(name, barId = 'bulk-action-bar', countId = 'bulk-count') {
    const n = document.querySelectorAll(`input.bulk-check[name="${name}"]:checked`).length;
    const bar = document.getElementById(barId);
    if (bar) bar.style.display = n > 0 ? 'flex' : 'none';
    const cnt = document.getElementById(countId);
    if (cnt) cnt.textContent = n;
}

function submitBulkDelete(formId, opts) {
    const name = opts.checkboxName || 'ids[]';
    const ids = [...document.querySelectorAll(`input.bulk-check[name="${name}"]:checked`)].map(el => el.value);
    if (!ids.length) {
        adminToast('Pilih minimal satu data.', 'info');
        return;
    }

    adminConfirm({
        type: 'danger',
        title: opts.title || 'Hapus Data Terpilih?',
        desc: opts.desc || `Yakin hapus ${ids.length} data? Tindakan ini tidak dapat dibatalkan.`,
        okLabel: opts.okLabel || 'Hapus',
        onConfirm: () => {
            const form = document.getElementById(formId);
            form.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
            ids.forEach(id => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'ids[]';
                inp.value = id;
                form.appendChild(inp);
            });
            adminShowLoading(opts.loadingText || 'Menghapus...');
            form.submit();
        }
    });
}

// Konfirmasi logout yang menampilkan modal custom
function confirmLogout() {
    adminConfirm({
        type: 'danger',
        title: 'Keluar?',
        desc: 'Apakah Anda yakin ingin logout?',
        okLabel: 'Logout',
        onConfirm: () => { document.getElementById('logout-form').submit(); }
    });
}

function confirmUpdateStatus(form, label) {
    adminConfirm({
        type: 'warning',
        icon: 'fa-pen-to-square',
        title: 'Ubah Status?',
        desc: `Anda akan mengubah ${label} transaksi ini. Lanjutkan?`,
        okLabel: 'Ya, Ubah',
        onConfirm: () => {
            adminShowLoading('Menyimpan perubahan...');
            form.submit();
        }
    });
}

/* ─── Modal helpers (with fade) ─────────────────────────────── */
function adminModalOpen(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = 'flex';
    requestAnimationFrame(() => el.classList.add('active'));
}

function adminModalClose(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('active');
    setTimeout(() => { el.style.display = ''; }, 260);
}
