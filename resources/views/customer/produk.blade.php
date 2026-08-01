@extends('layouts.customer')

@section('title', 'Katalog Produk — Toko Sinar Manis')

@section('content')

<section class="page-header">
    <div class="container">
        <h1 style="font-size:2rem;font-weight:800;color:var(--primary);">Katalog Produk</h1>
        <p class="text-sm text-muted" style="margin-top:.25rem;">Pilih produk kebutuhan harian Anda dari Toko Sinar Manis.</p>
    </div>
</section>

<section style="padding:1.5rem 0 4rem;">
    <div class="container">
        <div class="filters-row">
            <div class="search-wrapper">
                <span class="search-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="search" class="form-control" placeholder="Cari produk...">
            </div>
            <select id="kategori" class="form-control" style="width:12rem;">
                <option value="">Semua kategori</option>
                @foreach($categories as $c)
                <option value="{{ $c->id_category }}">{{ $c->nama_category }}</option>
                @endforeach
            </select>
            <select id="sort" class="form-control" style="width:13rem;">
                <option value="terlaris">Terlaris</option>
                <option value="terbaru">Terbaru</option>
                <option value="termurah">Harga termurah</option>
                <option value="termahal">Harga termahal</option>
            </select>
        </div>

        <div class="filters-count" id="product-count">Memuat produk...</div>
        <div id="product-grid" class="grid-4" style="margin-top:1.25rem;"></div>
        <div id="pagination-controls" style="margin-top: 2.5rem; display: flex; justify-content: center; gap: 0.5rem; align-items: center;"></div>
        <div id="loading" style="display:none; text-align:center; padding:3rem 0;">
            <i class="fa-solid fa-spinner fa-spin fa-2x text-muted"></i>
        </div>
        <div id="empty-state" style="display:none;">
            <div class="card card-dashed" style="margin-top:1.5rem;">
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-solid fa-box-open"></i></div>
                    <div class="empty-title">Tidak ada produk ditemukan</div>
                    <p class="empty-desc">Coba ubah filter atau kata kunci pencarian Anda.</p>
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
const emojiMap = {'Sembako':'🛒','Makanan Instan':'🍜','Minuman':'🧃','Kebersihan':'🧼','Kebutuhan Mandi & Cuci':'🧴'};
function getEmoji(cat) { return emojiMap[cat] || '📦'; }

function escapeHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function productImageHtml(p) {
    if (!p.foto) return `<span style="font-size:3rem;">${getEmoji(p.nama_category)}</span>`;
    return `<img src="/storage/${encodeURI(p.foto)}" alt="${escapeHtml(p.nama_product)}">`;
}

function starHtml(avgRating, reviewCount) {
    if (!reviewCount || reviewCount < 1) return '';
    const full = Math.floor(avgRating), half = avgRating - full >= 0.5 ? 1 : 0, empty = 5 - full - half;
    let html = '<span style="display:inline-flex;align-items:center;gap:1px;">';
    for (let i=0;i<full;i++) html += '<i class="fa-solid fa-star" style="color:#f59e0b;font-size:.65rem;"></i>';
    if (half) html += '<i class="fa-solid fa-star-half-stroke" style="color:#f59e0b;font-size:.65rem;"></i>';
    for (let i=0;i<empty;i++) html += '<i class="fa-regular fa-star" style="color:#d1d5db;font-size:.65rem;"></i>';
    html += `<span style="color:var(--muted-foreground);font-size:.7rem;margin-left:4px;">(${reviewCount})</span></span>`;
    return html;
}

const stockMap = {};
let currentPage = 1;

document.addEventListener('DOMContentLoaded', () => {
    loadProducts();
    document.getElementById('search').addEventListener('input', debounce(handleFilterChange, 400));
    document.getElementById('kategori').addEventListener('change', handleFilterChange);
    document.getElementById('sort').addEventListener('change', handleFilterChange);
});

function debounce(fn, ms) { let t; return function() { clearTimeout(t); t = setTimeout(() => fn.apply(this, arguments), ms); }; }

async function handleFilterChange() { currentPage = 1; await loadProducts(); }
async function loadPage(page) { currentPage = page; await loadProducts(); window.scrollTo({ top: 0, behavior: 'smooth' }); }

async function loadProducts() {
    const search = document.getElementById('search').value;
    const kategori = document.getElementById('kategori').value;
    const sort = document.getElementById('sort').value;
    const grid = document.getElementById('product-grid');
    const loading = document.getElementById('loading');
    const empty = document.getElementById('empty-state');
    const count = document.getElementById('product-count');
    grid.innerHTML = ''; loading.style.display = 'block'; empty.style.display = 'none'; count.textContent = '';
    const pagination = document.getElementById('pagination-controls');
    if (pagination) pagination.innerHTML = '';

    try {
        const res = await fetch(`/api/produk?search=${encodeURIComponent(search)}&kategori=${kategori}&sort=${sort}&page=${currentPage}&limit=12`);
        const result = await res.json();
        loading.style.display = 'none';
        if (!result.status || result.data.length === 0) { empty.style.display = 'block'; count.textContent = 'Menampilkan 0 produk'; return; }

        const startIdx = (result.page - 1) * result.limit + 1;
        const endIdx = Math.min(result.page * result.limit, result.total);
        count.textContent = `Menampilkan ${startIdx}-${endIdx} dari ${result.total} produk`;

        result.data.forEach(p => {
            const stok = parseInt(p.stok), isOut = stok <= 0;
            stockMap[p.id_product] = stok;
            const btn = isOut
                ? `<button id="btn-${p.id_product}" disabled class="btn btn-primary btn-sm" style="opacity:0.5;cursor:not-allowed;"><i class="fa-solid fa-box-open"></i> Habis</button>`
                : `{!! Auth::check() && Auth::user()->role === 'admin' ? '<button id="btn-${p.id_product}" disabled class="btn btn-primary btn-sm" style="opacity:0.5;cursor:not-allowed;"><i class="fa-solid fa-lock"></i> Admin</button>' : "<button id=\"btn-\${p.id_product}\" onclick=\"addToCart(\${p.id_product})\" class=\"btn btn-primary btn-sm\"><i class=\"fa-solid fa-cart-plus\"></i> Tambah</button>" !!}`;
            grid.innerHTML += `<div class="product-card" id="card-${p.id_product}">
                <div class="product-card-image"><span class="product-card-badge">${escapeHtml(p.nama_category)}</span>${productImageHtml(p)}</div>
                <div class="product-card-body">
                    <div class="product-card-name">${escapeHtml(p.nama_product)}</div>
                    <div class="product-card-meta">${starHtml(p.avg_rating, p.review_count)}${p.review_count > 0 ? '<span>&middot;</span>' : ''}<span>Terjual ${p.terjual}</span></div>
                    <div class="product-card-price">${formatRupiah(p.harga)}</div>
                    <div style="display:flex;justify-content:space-between;font-size:.7rem;color:var(--muted-foreground);margin-top:.25rem;">
                        <span></span><span id="stok-${p.id_product}" style="font-weight:600;transition:color .3s;${isOut?'color:#ef4444;':''}">Stok ${stok}</span></div>
                    <div class="product-card-footer">
                        <a href="/produk/${p.id_product}" class="btn btn-outline btn-sm btn-block">Detail</a>${btn}</div></div></div>`;
        });
        renderPagination(result.page, result.total_pages);
    } catch(e) { loading.style.display = 'none'; showToast('Gagal memuat produk'); }
}

function renderPagination(current, total) {
    const container = document.getElementById('pagination-controls');
    if (!container || total <= 1) return;
    let html = '';
    if (current > 1) html += `<button class="btn btn-outline btn-sm" onclick="loadPage(${current-1})"><i class="fa-solid fa-chevron-left"></i></button>`;
    else html += `<button class="btn btn-outline btn-sm" disabled style="opacity:0.5;cursor:not-allowed;"><i class="fa-solid fa-chevron-left"></i></button>`;
    for (let i = 1; i <= total; i++) {
        if (total > 7) {
            if (i===1||i===total||(i>=current-1&&i<=current+1)) html += renderPageBtn(i,current);
            else if (i===current-2||i===current+2) html += '<span style="padding:0.5rem;color:var(--muted-foreground);">...</span>';
        } else html += renderPageBtn(i,current);
    }
    if (current < total) html += `<button class="btn btn-outline btn-sm" onclick="loadPage(${current+1})"><i class="fa-solid fa-chevron-right"></i></button>`;
    else html += `<button class="btn btn-outline btn-sm" disabled style="opacity:0.5;cursor:not-allowed;"><i class="fa-solid fa-chevron-right"></i></button>`;
    container.innerHTML = html;
}
function renderPageBtn(i,current) { return i===current ? `<button class="btn btn-primary btn-sm" style="pointer-events:none;">${i}</button>` : `<button class="btn btn-outline btn-sm" onclick="loadPage(${i})">${i}</button>`; }

const cartChannel = new BroadcastChannel('cart_stock_sync');
cartChannel.onmessage = (event) => {
    if (event.data.type === 'stock_update') {
        const { productId, newStok } = event.data;
        stockMap[productId] = newStok;
        const stokEl = document.getElementById(`stok-${productId}`), btn = document.getElementById(`btn-${productId}`);
        if (stokEl) { stokEl.textContent = `Stok ${newStok}`; if (newStok<=0) stokEl.style.color='#ef4444'; else stokEl.style.color=''; }
        if (btn) {
            if (newStok<=0) { btn.disabled=true; btn.style.opacity='0.5'; btn.style.cursor='not-allowed'; btn.onclick=null; btn.innerHTML='<i class="fa-solid fa-box-open"></i> Habis'; }
            else { btn.disabled=false; btn.style.opacity='1'; btn.style.cursor=''; btn.onclick=()=>addToCart(productId); btn.innerHTML='<i class="fa-solid fa-cart-plus"></i> Tambah'; }
        }
    }
};

async function addToCart(productId, qty=1) {
    const btn = document.getElementById(`btn-${productId}`);
    if (btn && btn.disabled) return;
    if (btn) { btn.disabled=true; btn.style.opacity='0.7'; }
    const res = await fetch('/api/cart', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({product_id:productId,qty}) });
    const data = await res.json();
    showToast(data.message||(data.status?'Ditambahkan':'Gagal'));
    if (data.status) {
        if (data.cart_count!==undefined) updateCartBadge(data.cart_count);
        const newStok = Math.max(0,(stockMap[productId]??1)-qty);
        stockMap[productId]=newStok;
        const stokEl=document.getElementById(`stok-${productId}`);
        if(stokEl){stokEl.textContent=`Stok ${newStok}`;if(newStok<=0)stokEl.style.color='#ef4444';}
        if(btn){if(newStok<=0){btn.disabled=true;btn.style.opacity='0.5';btn.style.cursor='not-allowed';btn.onclick=null;btn.innerHTML='<i class="fa-solid fa-box-open"></i> Habis';}else{btn.disabled=false;btn.style.opacity='1';btn.style.cursor='';}}
        cartChannel.postMessage({type:'stock_update',productId,newStok});
    } else { if(btn){btn.disabled=false;btn.style.opacity='1';btn.style.cursor='';} }
}
</script>
@endpush

@endsection
