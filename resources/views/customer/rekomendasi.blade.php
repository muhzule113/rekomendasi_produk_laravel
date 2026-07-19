@extends('layouts.customer')

@section('title', 'Rekomendasi Produk — Toko Sinar Manis')

@section('content')

@php
$emojiMap = ['Sembako'=>'🛒','Makanan Instan'=>'🍜','Minuman'=>'🧃','Kebersihan'=>'🧼','Kebutuhan Mandi & Cuci'=>'🧴','default'=>'📦'];
function getEmojiRec(string $cat, array $map): string { return $map[$cat] ?? $map['default']; }

$isLoggedIn = Auth::check() && Auth::user()->role === 'pelanggan';
@endphp

<!-- Dark Header -->
<section class="page-header-dark">
    <div class="container">
        <div style="display:inline-flex;align-items:center;gap:.5rem;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.05);padding:.25rem .75rem;border-radius:9999px;font-size:.75rem;color:rgba(255,255,255,.8);">
            <i class="fa-solid fa-brain" style="color:var(--gold);font-size:.7rem;"></i>
            {{ $method }}
        </div>
        <h1 style="margin-top:1rem;font-size:2rem;font-weight:800;color:white;">Rekomendasi Produk untuk Anda</h1>
        <p style="margin-top:.75rem;max-width:42rem;font-size:.875rem;color:rgba(255,255,255,.75);line-height:1.6;">
            Rekomendasi ini dibuat berdasarkan pola pembelian dan kemiripan produk dari transaksi pelanggan.
            Sistem mempelajari produk apa yang sering dibeli bersama, lalu menyarankan produk yang paling relevan untuk Anda.
        </p>
    </div>
</section>

<!-- Content -->
<section style="padding:2rem 0 4rem;">
    <div class="container">

        <!-- Section 1: Berdasarkan Transaksi Anda -->
        <div style="margin-bottom: 3rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-user-check" style="color: var(--primary);"></i> Berdasarkan Transaksi Anda
            </h2>

            @if(!$isLoggedIn)
            <!-- Alert: not logged in -->
            <div class="alert-card mb-6">
                <div class="alert-card-left">
                    <i class="fa-solid fa-sparkles alert-card-icon"></i>
                    <div>
                        <div class="alert-card-title">Anda belum masuk</div>
                        <div class="alert-card-desc">Silakan masuk untuk melihat rekomendasi yang dipersonalisasi khusus untuk Anda.</div>
                    </div>
                </div>
                <a href="{{ route('login') }}" class="btn btn-primary btn-md" style="white-space:nowrap;">Masuk untuk Rekomendasi Personal</a>
            </div>
            @elseif(empty($recommendations) || count($recommendations) === 0)
            <div class="alert-card mb-6" style="background: rgba(241,245,249,0.5); border-color: #e2e8f0; color: var(--text-muted);">
                <div class="alert-card-left">
                    <i class="fa-solid fa-cart-arrow-down alert-card-icon" style="color: #94a3b8; background: white;"></i>
                    <div>
                        <div class="alert-card-title" style="color: var(--text-dark);">Belum ada produk yang tersedia</div>
                        <div class="alert-card-desc">Maaf, saat ini sistem tidak dapat menemukan produk untuk direkomendasikan.</div>
                    </div>
                </div>
            </div>
            @else

            @if($message)
            <!-- Alert: Fallback Message -->
            <div class="alert-card mb-6" style="background: #eff6ff; border-color: #bfdbfe;">
                <div class="alert-card-left">
                    <i class="fa-solid fa-lightbulb alert-card-icon" style="color: #2563eb;"></i>
                    <div>
                        <div class="alert-card-title" style="color: #1e3a8a;">Rekomendasi Alternatif</div>
                        <div class="alert-card-desc" style="color: #1d4ed8;">{{ $message }}</div>
                    </div>
                </div>
            </div>
            @endif

            <div class="grid-3">
                @foreach($recommendations as $p)
                <div class="rec-card">
                    <div class="rec-card-top">
                        <div class="rec-card-icon">{{ getEmojiRec($p['nama_category'], $emojiMap) }}</div>
                        <div>
                            <div class="rec-card-name">{{ $p['nama_product'] }}</div>
                            <div class="rec-card-category">{{ $p['nama_category'] }}</div>
                            <div class="rec-card-reason">
                                <i class="fa-solid fa-bolt" style="color:var(--gold);font-size:.65rem;"></i>
                                {{ $p['alasan'] }}
                            </div>
                        </div>
                    </div>
                    <div class="rec-card-bottom">
                        <div class="rec-card-price">{{ \App\Helpers\Helpers::formatRupiah($p['harga']) }}</div>
                        <button data-btn-prod="{{ $p['id_product'] }}"
                            class="btn btn-primary btn-sm"
                            style="{{ ($p['stok'] <= 0 || (Auth::check() && Auth::user()->role === 'admin')) ? 'opacity:0.5;cursor:not-allowed;' : '' }}"
                            {{ ($p['stok'] <= 0 || (Auth::check() && Auth::user()->role === 'admin')) ? 'disabled' : '' }}
                            {{ Auth::check() && Auth::user()->role === 'admin' ? '' : "onclick=addToCart(".$p['id_product'].")" }}>
                            @if($p['stok'] > 0 && (!Auth::check() || Auth::user()->role !== 'admin'))
                            <i class="fa-solid fa-cart-shopping"></i> Tambah
                            @elseif($p['stok'] <= 0)
                            <i class="fa-solid fa-box-open"></i> Habis
                            @else
                            <i class="fa-solid fa-lock"></i> Admin
                            @endif
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        <!-- Section 2: Berdasarkan Pelanggan Lain -->
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-users" style="color: var(--primary);"></i> Pilihan Pelanggan Lain
            </h2>
            <div class="grid-3">
                @foreach($popular as $p)
                <div class="rec-card">
                    <div class="rec-card-top">
                        <div class="rec-card-icon">{{ getEmojiRec($p['nama_category'], $emojiMap) }}</div>
                        <div>
                            <div class="rec-card-name">{{ $p['nama_product'] }}</div>
                            <div class="rec-card-category">{{ $p['nama_category'] }}</div>
                            <div class="rec-card-reason">
                                <i class="fa-solid fa-fire" style="color:#ef4444;font-size:.65rem;"></i>
                                {{ $p['alasan'] }}
                            </div>
                        </div>
                    </div>
                    <div class="rec-card-bottom">
                        <div class="rec-card-price">{{ \App\Helpers\Helpers::formatRupiah($p['harga']) }}</div>
                        <button data-btn-prod="{{ $p['id_product'] }}"
                            class="btn btn-primary btn-sm"
                            style="{{ ($p['stok'] <= 0 || (Auth::check() && Auth::user()->role === 'admin')) ? 'opacity:0.5;cursor:not-allowed;' : '' }}"
                            {{ ($p['stok'] <= 0 || (Auth::check() && Auth::user()->role === 'admin')) ? 'disabled' : '' }}
                            {{ Auth::check() && Auth::user()->role === 'admin' ? '' : "onclick=addToCart(".$p['id_product'].")" }}>
                            @if($p['stok'] > 0 && (!Auth::check() || Auth::user()->role !== 'admin'))
                            <i class="fa-solid fa-cart-shopping"></i> Tambah
                            @elseif($p['stok'] <= 0)
                            <i class="fa-solid fa-box-open"></i> Habis
                            @else
                            <i class="fa-solid fa-lock"></i> Admin
                            @endif
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- How it works card -->
        <div class="card" style="margin-top:2.5rem; background:rgba(241,245,249,.5);">
            <div class="card-body">
                <div style="font-family:var(--font-display);font-size:1.05rem;font-weight:700;color:var(--primary);">Bagaimana rekomendasi dihasilkan?</div>
                <div class="grid-3" style="margin-top:.875rem;">
                    @php
                    $steps = [
                        'Sistem mengumpulkan seluruh data transaksi pelanggan.',
                        'Hubungan antarproduk dihitung dengan cosine similarity.',
                        'Produk dengan skor kemiripan tertinggi disarankan kepada Anda.'
                    ];
                    @endphp
                    @foreach($steps as $i => $step)
                    <div class="card" style="padding:1rem;">
                        <div style="display:inline-flex;align-items:center;justify-content:center;width:1.5rem;height:1.5rem;border-radius:9999px;background:var(--primary);color:white;font-size:.75rem;font-weight:700;margin-bottom:.5rem;">{{ $i+1 }}</div>
                        <div class="text-sm text-muted">{{ $step }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
const stockMap = @json($stockMap ?? []);
const cartChannel = new BroadcastChannel('cart_stock_sync');

// Listen for updates from other tabs
cartChannel.onmessage = (event) => {
    if (event.data.type === 'stock_update') {
        const { productId, newStok } = event.data;
        stockMap[productId] = newStok;
        updateButtonsUI(productId, newStok);
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
            btn.removeAttribute('onclick');
        } else {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = '';
            btn.innerHTML = '<i class="fa-solid fa-cart-shopping"></i> Tambah';
            btn.setAttribute('onclick', `addToCart(${productId})`);
        }
    });
}

async function addToCart(productId, qty = 1) {
    @if(Auth::check() && Auth::user()->role === 'admin')
    showToast('Admin tidak dapat menambah produk ke keranjang.');
    return;
    @endif
    const buttons = document.querySelectorAll(`button[data-btn-prod="${productId}"]`);

    buttons.forEach(btn => {
        btn.disabled = true;
        btn.style.opacity = '0.7';
    });

    try {
        const res = await fetch('/api/cart', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ product_id: productId, qty })
        });
        const data = await res.json();
        showToast(data.message || (data.status ? 'Ditambahkan' : 'Gagal'));

        if (data.status) {
            if (data.cart_count !== undefined) updateCartBadge(data.cart_count);

            // Prefer server remaining_stok; fall back to local stockMap
            const current = Number(stockMap[productId] ?? 0);
            const newStok = data.remaining_stok !== undefined
                ? Number(data.remaining_stok)
                : Math.max(0, current - qty);

            stockMap[productId] = newStok;
            updateButtonsUI(productId, newStok);
            cartChannel.postMessage({ type: 'stock_update', productId: productId, newStok: newStok });
        } else {
            updateButtonsUI(productId, Number(stockMap[productId] ?? 0));
        }
    } catch (e) {
        showToast('Gagal menambah ke keranjang');
        updateButtonsUI(productId, Number(stockMap[productId] ?? 0));
    }
}
</script>
@endpush

@endsection
