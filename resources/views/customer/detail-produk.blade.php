@extends('layouts.customer')

@section('title', $product->nama_product . ' — Toko Sinar Manis')

@section('content')

@php
$emojiMap = ['Sembako'=>'🛒','Makanan Instan'=>'🍜','Minuman'=>'🧃','Kebersihan'=>'🧼','Kebutuhan Mandi & Cuci'=>'🧴','default'=>'📦'];
function getEmojiDetail(string $cat, array $map): string { return $map[$cat] ?? $map['default']; }

$cart = session('cart', []);
$cartQty = $cart[$product->id_product] ?? 0;
$productStok = max(0, (int)$product->stok - (int)$cartQty);

$isPelanggan = !Auth::check() || Auth::user()->role === 'pelanggan';
@endphp

@php
function starIconsDetail(int $rating): string {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $rating
            ? '<i class="fa-solid fa-star" style="color:#f59e0b;font-size:.8rem;"></i>'
            : '<i class="fa-regular fa-star" style="color:#d1d5db;font-size:.8rem;"></i>';
    }
    return $html;
}
@endphp

<section class="page-header">
    <div class="container">
        <h1 style="font-size:2rem;font-weight:800;color:var(--primary);">Detail Produk</h1>
        <p class="text-sm text-muted" style="margin-top:.25rem;">Informasi lengkap mengenai produk pilihan Anda.</p>
    </div>
</section>

<section style="padding: 3rem 0 5rem;">
    <div class="container">
        <div class="card card-body" style="display:flex;flex-wrap:wrap;gap:2rem;">
            <!-- Left: Image Box -->
            <div style="flex:1;min-width:300px;background:var(--secondary);border-radius:var(--radius);display:grid;place-items:center;min-height:300px;font-size:6rem;">
                {{ getEmojiDetail($product->nama_category, $emojiMap) }}
            </div>

            <!-- Right: Details -->
            <div style="flex:1.5;min-width:300px;display:flex;flex-direction:column;">
                <div style="display:inline-flex;align-items:center;padding:.2rem .75rem;border-radius:9999px;background:rgba(15,42,71,.08);color:var(--primary);font-size:.75rem;font-weight:600;margin-bottom:1rem;width:max-content;">
                    {{ $product->nama_category }}
                </div>
                <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:800;color:var(--primary);line-height:1.2;margin-bottom:.5rem;">
                    {{ $product->nama_product }}
                </h1>
                <div style="display:flex;align-items:center;gap:1rem;font-size:.875rem;color:var(--muted-foreground);margin-bottom:1.5rem;">
                    @if($ratingStats->review_count > 0)
                    <span style="display:flex;align-items:center;gap:.2rem;">{!! starIconsDetail(round($ratingStats->avg_rating)) !!} <span style="font-weight:600;color:var(--primary);margin-left:.25rem;">{{ $ratingStats->avg_rating }}</span></span>
                    <span>&middot;</span>
                    @endif
                    <span>Terjual {{ $product->terjual }}</span>
                    <span>&middot;</span>
                    <span id="stok_label" style="font-weight:600;transition:color .3s;">Stok sisa <span id="stok_value">{{ $productStok }}</span></span>
                </div>
                <div style="font-family:var(--font-display);font-size:2.5rem;font-weight:800;color:var(--primary);margin-bottom:2rem;">
                    {{ \App\Helpers\Helpers::formatRupiah($product->harga) }}
                </div>

                <p style="font-size:.9375rem;line-height:1.6;color:var(--muted-foreground);margin-bottom:2.5rem;">
                    {!! nl2br(e($product->deskripsi)) !!}
                </p>

                @if($isPelanggan)
                <div id="cart_action_wrap" style="display:flex;align-items:center;gap:1rem;margin-top:auto;flex-wrap:wrap;">
                    <div id="qty_wrap" style="display: {{ $productStok > 0 ? 'flex' : 'none' }};align-items:center;border:1px solid var(--border);border-radius:var(--radius);height:3rem;">
                        <button onclick="updateDetQty(-1)" class="btn btn-ghost" style="height:100%;width:3rem;">-</button>
                        <input type="text" id="qty_input" class="form-control" value="1" readonly style="width:3.5rem;height:100%;border:none;text-align:center;border-radius:0;">
                        <button onclick="updateDetQty(1)" class="btn btn-ghost" style="height:100%;width:3rem;">+</button>
                    </div>
                    <button id="add_cart_btn" onclick="addDetToCart({{ $product->id_product }})"
                        class="btn btn-primary btn-xl" style="flex:1;min-width:200px;{{ $productStok <= 0 ? 'opacity:.5;cursor:not-allowed;' : '' }}"
                        {{ $productStok <= 0 ? 'disabled' : '' }}>
                        @if($productStok > 0)
                        <i class="fa-solid fa-cart-plus" id="btn_icon" style="margin-right:.5rem;"></i>
                        <span id="btn_text">Tambah ke Keranjang</span>
                        @else
                        <i class="fa-solid fa-box-open" id="btn_icon" style="margin-right:.5rem;"></i>
                        <span id="btn_text">Produk Habis</span>
                        @endif
                    </button>
                </div>
                @else
                <div style="margin-top:auto;padding:1rem;background:var(--secondary);border-radius:var(--radius);color:var(--muted-foreground);font-size:.875rem;text-align:center;">
                    <i class="fa-solid fa-lock"></i> Hanya pelanggan yang dapat menambahkan produk ke keranjang.
                </div>
                @endif
            </div>
        </div>
    </div>
</section>

@if(count($similarProducts) > 0)
<section style="padding: 3rem 0; background: var(--secondary);">
    <div class="container">
        <div class="flex items-end justify-between mb-6">
            <div>
                <div class="section-label">Rekomendasi</div>
                <h2 style="font-size:1.75rem;font-weight:800;color:var(--primary);margin-top:.25rem;">Sering Dibeli Bersamaan</h2>
            </div>
        </div>
        <div class="grid-4">
            @foreach($similarProducts as $p)
            <div class="product-card" style="background:white;">
                <div class="product-card-image">
                    <span class="product-card-badge">{{ $p['nama_category'] }}</span>
                    <span style="font-size:3rem;">{{ getEmojiDetail($p['nama_category'], $emojiMap) }}</span>
                </div>
                <div class="product-card-body">
                    <div class="product-card-name">{{ $p['nama_product'] }}</div>
                    <div class="product-card-meta">
                        <span>Terjual {{ $p['terjual'] }}</span>
                    </div>
                    <div class="product-card-price">{{ \App\Helpers\Helpers::formatRupiah($p['harga']) }}</div>
                    <div class="product-card-footer">
                        <a href="{{ route('produk.detail', $p['id_product']) }}" class="btn btn-outline btn-sm btn-block">Detail</a>
                        @if($isPelanggan)
                        <button data-btn-prod="{{ $p['id_product'] }}" onclick="addToCartRec({{ $p['id_product'] }})"
                            class="btn btn-primary btn-sm"
                            style="{{ $p['stok'] <= 0 ? 'opacity:0.5;cursor:not-allowed;' : '' }}"
                            {{ $p['stok'] <= 0 ? 'disabled' : '' }}>
                            @if($p['stok'] > 0)
                            <i class="fa-solid fa-cart-plus"></i> Tambah
                            @else
                            <i class="fa-solid fa-box-open"></i> Habis
                            @endif
                        </button>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if(count($sameCategory) > 0)
<section style="padding: 3rem 0;">
    <div class="container">
        <div class="flex items-end justify-between mb-6">
            <div>
                <div class="section-label">Kategori Serupa</div>
                <h2 style="font-size:1.75rem;font-weight:800;color:var(--primary);margin-top:.25rem;">Pilihan Lainnya</h2>
            </div>
            <a href="{{ route('produk', ['cat' => $product->id_category]) }}" class="btn btn-outline btn-md">
                Lihat semua <i class="fa-solid fa-arrow-right" style="font-size:.75rem;"></i>
            </a>
        </div>
        <div class="grid-4">
            @foreach($sameCategory as $p)
            <div class="product-card">
                <div class="product-card-image">
                    <span class="product-card-badge">{{ $p->nama_category }}</span>
                    <span style="font-size:3rem;">{{ getEmojiDetail($p->nama_category, $emojiMap) }}</span>
                </div>
                <div class="product-card-body">
                    <div class="product-card-name">{{ $p->nama_product }}</div>
                    <div class="product-card-meta">
                        <span>Terjual {{ $p->terjual }}</span>
                    </div>
                    <div class="product-card-price">{{ \App\Helpers\Helpers::formatRupiah($p->harga) }}</div>
                    <div class="product-card-footer">
                        <a href="{{ route('produk.detail', $p->id_product) }}" class="btn btn-outline btn-sm btn-block">Detail</a>
                        @if($isPelanggan)
                        <button data-btn-prod="{{ $p->id_product }}" onclick="addToCartRec({{ $p->id_product }})"
                            class="btn btn-primary btn-sm"
                            style="{{ $p->stok <= 0 ? 'opacity:0.5;cursor:not-allowed;' : '' }}"
                            {{ $p->stok <= 0 ? 'disabled' : '' }}>
                            @if($p->stok > 0)
                            <i class="fa-solid fa-cart-plus"></i> Tambah
                            @else
                            <i class="fa-solid fa-box-open"></i> Habis
                            @endif
                        </button>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- ====== Rating & Ulasan ====== -->
<section style="padding: 3rem 0;background:var(--secondary);">
    <div class="container" style="max-width:800px;">
        <div class="flex items-end justify-between mb-6">
            <div>
                <div class="section-label">Ulasan Pelanggan</div>
                <h2 style="font-size:1.5rem;font-weight:800;color:var(--primary);margin-top:.25rem;">
                    @if($ratingStats->review_count > 0)
                        {!! starIconsDetail(round($ratingStats->avg_rating)) !!} <span style="margin-left:.5rem;">{{ $ratingStats->avg_rating }}</span> <span style="font-size:.9rem;color:var(--muted-foreground);">({{ $ratingStats->review_count }} ulasan)</span>
                    @else
                        Belum ada ulasan
                    @endif
                </h2>
            </div>
        </div>

        @if($ratingStats->review_count > 0)
        <div style="display:flex;flex-direction:column;gap:1rem;margin-bottom:2rem;">
            @foreach($reviews as $rv)
            <div class="card card-body" style="padding:1rem 1.25rem;">
                <div class="flex justify-between items-center" style="margin-bottom:.35rem;">
                    <div class="flex items-center gap-2">
                        <span class="font-bold" style="color:var(--primary);">{{ $rv->nama }}</span>
                        <span style="display:flex;align-items:center;gap:1px;">{!! starIconsDetail($rv->rating) !!}</span>
                    </div>
                    <span style="font-size:.75rem;color:var(--muted-foreground);">{{ \Carbon\Carbon::parse($rv->created_at)->format('d M Y') }}</span>
                </div>
                @if(!empty($rv->komentar))
                <p style="font-size:.875rem;color:#555;margin:0;line-height:1.5;">{{ $rv->komentar }}</p>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        <!-- Submit Review -->
        <div class="card card-body" id="review-form-card">
            <h3 style="font-size:1rem;font-weight:700;color:var(--primary);margin-bottom:1rem;">
                {{ $userHasReviewed ? 'Anda sudah memberikan ulasan' : 'Beri Ulasan' }}
            </h3>
            @if(!Auth::check())
                <p style="color:var(--muted-foreground);font-size:.875rem;">
                    <a href="{{ route('login') }}" style="color:var(--primary);font-weight:600;">Login</a> untuk memberikan ulasan.
                </p>
            @elseif($userHasReviewed)
                <p style="color:var(--muted-foreground);font-size:.875rem;">Terima kasih! Ulasan Anda sudah tercatat.</p>
            @else
                <div id="star-picker" style="margin-bottom:1rem;display:flex;gap:.3rem;">
                    @for($s = 1; $s <= 5; $s++)
                    <span onclick="setRating({{ $s }})" id="star-{{ $s }}" class="star-pick" style="font-size:1.5rem;cursor:pointer;color:#d1d5db;transition:color .15s;">
                        <i class="fa-regular fa-star"></i>
                    </span>
                    @endfor
                </div>
                <textarea id="review-komentar" class="form-control" placeholder="Tulis komentar (opsional)..." rows="2" style="width:100%;margin-bottom:.75rem;font-size:.85rem;"></textarea>
                <button onclick="submitReview()" id="btn-submit-review" class="btn btn-primary btn-sm" disabled>
                    <i class="fa-solid fa-paper-plane"></i> Kirim Ulasan
                </button>
                <span id="review-msg" style="font-size:.8rem;margin-left:.75rem;display:none;"></span>
            @endif
        </div>
    </div>
</section>

@push('scripts')
<script>
let qty = 1;
let currentStok = {{ $productStok }};

function updateDetQty(change) {
    qty += change;
    if (qty < 1) qty = 1;
    if (qty > currentStok) qty = currentStok;
    document.getElementById('qty_input').value = qty;
}

const stockMap = @json($stockMap ?? []);
const cartChannel = new BroadcastChannel('cart_stock_sync');

// Listen for updates from other tabs
cartChannel.onmessage = (event) => {
    if (event.data.type === 'stock_update') {
        const { productId, newStok } = event.data;
        if (productId == {{ $product->id_product }}) {
            updateStokUI(newStok);
        }

        if (stockMap[productId] !== undefined) {
            stockMap[productId] = newStok;
            updateButtonsUI(productId, newStok);
        }
    }
};

function updateButtonsUI(productId, newStok) {
    const buttons = document.querySelectorAll(`button[data-btn-prod="${productId}"]`);
    buttons.forEach(btn => {
        if (newStok <= 0) {
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
            btn.innerHTML = '<i class="fa-solid fa-box-open"></i> Habis';
        } else {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = '';
            btn.innerHTML = '<i class="fa-solid fa-cart-plus"></i> Tambah';
        }
    });
}

function updateStokUI(newStok) {
    currentStok = newStok;
    const stokValue = document.getElementById('stok_value');
    const stokLabel = document.getElementById('stok_label');
    const btn = document.getElementById('add_cart_btn');
    const btnIcon = document.getElementById('btn_icon');
    const btnText = document.getElementById('btn_text');
    const qtyWrap = document.getElementById('qty_wrap');

    // Update angka stok
    if (stokValue) stokValue.textContent = newStok;

    if (newStok <= 0) {
        // Stok habis: label merah
        if (stokLabel) stokLabel.style.color = '#ef4444';

        // Disable tombol
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.style.cursor = 'not-allowed';
        if (btnIcon) { btnIcon.className = 'fa-solid fa-box-open'; btnIcon.style.marginRight = '.5rem'; }
        if (btnText) btnText.textContent = 'Produk Habis';

        // Sembunyikan qty control
        if (qtyWrap) qtyWrap.style.display = 'none';
    } else {
        if (stokLabel) stokLabel.style.color = '';
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = '';
        if (btnIcon) { btnIcon.className = 'fa-solid fa-cart-plus'; btnIcon.style.marginRight = '.5rem'; }
        if (btnText) btnText.textContent = 'Tambah ke Keranjang';
        if (qtyWrap) qtyWrap.style.display = 'flex';

        // Pastikan qty tidak melebihi stok baru
        if (qty > newStok) {
            qty = newStok;
            const qtyInput = document.getElementById('qty_input');
            if (qtyInput) qtyInput.value = qty;
        }
    }
}

async function addDetToCart(productId) {
    const btn = document.getElementById('add_cart_btn');
    if (btn.disabled) return;

    // Disable sementara untuk mencegah double-click
    btn.disabled = true;
    btn.style.opacity = '0.7';

    const res = await fetch('/api/cart', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, qty: qty })
    });
    const data = await res.json();
    showToast(data.message || (data.status ? 'Ditambahkan ke keranjang' : 'Gagal'));

    if (data.status) {
        // Kurangi stok secara realtime
        const newStok = currentStok - qty;
        updateStokUI(newStok);
        if (data.cart_count !== undefined) updateCartBadge(data.cart_count);

        // Broadcast update
        cartChannel.postMessage({ type: 'stock_update', productId: productId, newStok: newStok });
    }

    // Re-enable jika stok masih ada
    if (currentStok > 0) {
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

async function addToCartRec(productId) {
    const qty = 1;
    const buttons = document.querySelectorAll(`button[data-btn-prod="${productId}"]`);

    buttons.forEach(btn => { btn.disabled = true; btn.style.opacity = '0.7'; });

    const res = await fetch('/api/cart', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({product_id: productId, qty})
    });
    const data = await res.json();
    showToast(data.message || (data.status ? 'Ditambahkan ke keranjang' : 'Gagal'));

    if (data.status) {
        if (data.cart_count !== undefined) updateCartBadge(data.cart_count);

        const newStok = Math.max(0, (stockMap[productId] ?? 1) - qty);
        stockMap[productId] = newStok;
        updateButtonsUI(productId, newStok);
        cartChannel.postMessage({ type: 'stock_update', productId: productId, newStok: newStok });
    } else {
        const currentStok = stockMap[productId] ?? 1;
        updateButtonsUI(productId, currentStok);
    }
}

// ── Rating & Review ──
let selectedRating = 0;

function setRating(r) {
    selectedRating = r;
    document.getElementById('btn-submit-review').disabled = false;
    for (let i = 1; i <= 5; i++) {
        const el = document.getElementById(`star-${i}`);
        if (el) {
            el.style.color = i <= r ? '#f59e0b' : '#d1d5db';
            el.innerHTML = i <= r ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
        }
    }
}

async function submitReview() {
    if (selectedRating < 1) return;
    const btn = document.getElementById('btn-submit-review');
    const msg = document.getElementById('review-msg');
    const komentar = document.getElementById('review-komentar').value;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim...';

    try {
        const res = await fetch('/api/review', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_product: {{ $product->id_product }},
                rating: selectedRating,
                komentar: komentar
            })
        });
        const data = await res.json();
        if (data.status) {
            msg.style.display = 'inline';
            msg.style.color = '#059669';
            msg.textContent = 'Ulasan terkirim!';
            setTimeout(() => location.reload(), 1500);
        } else {
            msg.style.display = 'inline';
            msg.style.color = '#dc2626';
            msg.textContent = data.message;
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Kirim Ulasan';
        }
    } catch (e) {
        msg.style.display = 'inline';
        msg.style.color = '#dc2626';
        msg.textContent = 'Gagal mengirim.';
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Kirim Ulasan';
    }
}
</script>
@endpush

@endsection
