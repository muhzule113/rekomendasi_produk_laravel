@extends('layouts.customer')

@section('title', 'Toko Sinar Manis — Rekomendasi Cerdas')

@section('content')

<section class="hero">
    <div class="hero-pattern"></div>
    <div class="hero-blob" style="width:24rem;height:24rem;top:-6rem;right:-8rem;"></div>
    <div class="hero-inner">
        <div>
            <div class="hero-badge">
                <i class="fa-solid fa-graduation-cap" style="color:var(--gold);font-size:.75rem;"></i>
                Studi Kasus Skripsi &middot; Item-Based Collaborative Filtering
            </div>
            <h1 class="hero-title">
                Belanja lebih mudah dengan
                <span class="gold"> rekomendasi produk</span>
                yang sesuai kebutuhan Anda.
            </h1>
            <p class="hero-desc">
                Toko Sinar Manis hadir sebagai sistem toko berbasis web dengan modul analisis transaksi pelanggan, sehingga setiap rekomendasi produk benar-benar relevan dengan kebiasaan belanja Anda.
            </p>
            <div class="hero-actions">
                <a href="{{ route('produk') }}" class="btn btn-gold btn-xl">
                    Lihat Produk <i class="fa-solid fa-arrow-right" style="font-size:.875rem;"></i>
                </a>
                @guest
                <a href="{{ route('login') }}" class="btn btn-outline-white btn-xl">Masuk / Daftar</a>
                @endguest
            </div>
        </div>
    </div>
</section>

<section style="padding: 4rem 0;">
    <div class="container">
        <div style="max-width:36rem; margin-bottom:2.5rem;">
            <div class="section-label">Keunggulan</div>
            <h2 style="font-size:1.75rem; font-weight:800; color:var(--primary); margin-top:.25rem;">Dirancang untuk pelanggan dan pemilik toko</h2>
            <p class="text-muted text-sm" style="margin-top:.5rem;">Antarmuka bersih, alur sederhana, dan modul rekomendasi yang transparan.</p>
        </div>
        <div class="grid-4">
            @php
            $features = [
                ['icon'=>'fa-bag-shopping',   'title'=>'Produk Lengkap',       'desc'=>'Beragam kebutuhan harian tersedia dalam satu katalog rapi.'],
                ['icon'=>'fa-stars',          'title'=>'Rekomendasi Personal', 'desc'=>'Saran produk berbasis pola transaksi pelanggan.'],
                ['icon'=>'fa-credit-card',    'title'=>'Transaksi Mudah',      'desc'=>'Tunai, transfer, atau QRIS — proses cepat dan jelas.'],
                ['icon'=>'fa-clock-rotate-left','title'=>'Riwayat Tersimpan', 'desc'=>'Semua pembelian Anda dicatat untuk analisis lebih baik.'],
            ];
            @endphp
            @foreach ($features as $f)
            <div class="card card-body">
                <div class="feature-icon"><i class="fa-solid {{ $f['icon'] }}"></i></div>
                <div class="feature-title">{{ $f['title'] }}</div>
                <div class="feature-desc">{{ $f['desc'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section style="padding-bottom:4rem;">
    <div class="container">
        <div class="flex items-end justify-between mb-6">
            <div>
                <div class="section-label">Produk Unggulan</div>
                <h2 style="font-size:1.75rem;font-weight:800;color:var(--primary);margin-top:.25rem;">Sering dibeli pelanggan</h2>
            </div>
            <a href="{{ route('produk') }}" class="btn btn-outline btn-md">
                Lihat semua <i class="fa-solid fa-arrow-right" style="font-size:.75rem;"></i>
            </a>
        </div>
        <div class="grid-4">
            @foreach ($featured as $p)
            @php
                $emojiMap = ['Sembako'=>'🛒','Makanan Instan'=>'🍜','Minuman'=>'🧃','Kebersihan'=>'🧼','Kebutuhan Mandi & Cuci'=>'🧴'];
                $emoji = $emojiMap[$p->nama_category] ?? '📦';
            @endphp
            <div class="product-card">
                <div class="product-card-image">
                    <span class="product-card-badge">{{ $p->nama_category }}</span>
                    @if($p->foto)
                        <img src="{{ asset('storage/'.$p->foto) }}" alt="{{ $p->nama_product }}">
                    @else
                        <span style="font-size:3rem;">{{ $emoji }}</span>
                    @endif
                </div>
                <div class="product-card-body">
                    <div class="product-card-name">{{ $p->nama_product }}</div>
                    <div class="product-card-meta">
                        @if($p->review_count > 0)
                        <span class="product-card-rating">
                            @for($i=1; $i<=5; $i++)
                                @if($i <= round($p->avg_rating))
                                <i class="fa-solid fa-star" style="color:#f59e0b;font-size:.65rem;"></i>
                                @else
                                <i class="fa-regular fa-star" style="color:#d1d5db;font-size:.65rem;"></i>
                                @endif
                            @endfor
                            <span style="font-weight:600;color:var(--primary);margin-left:.25rem;">{{ $p->avg_rating }}</span>
                        </span>
                        <span>&middot;</span>
                        @endif
                        <span>Terjual {{ $p->terjual }}</span>
                    </div>
                    <div class="product-card-price">{{ \App\Helpers\Helpers::formatRupiah($p->harga) }}</div>
                    <div class="product-card-footer">
                        <a href="{{ route('produk.detail', $p->id_product) }}" class="btn btn-outline btn-sm btn-block">Detail</a>
                        <button data-btn-prod="{{ $p->id_product }}" onclick="addToCart({{ $p->id_product }})"
                            class="btn btn-primary btn-sm"
                            style="{{ $p->stok <= 0 || (Auth::check() && Auth::user()->role === 'admin') ? 'opacity:0.5;cursor:not-allowed;' : '' }}"
                            {{ $p->stok <= 0 || (Auth::check() && Auth::user()->role === 'admin') ? 'disabled' : '' }}>
                            @if($p->stok > 0 && (!Auth::check() || Auth::user()->role !== 'admin'))
                            <i class="fa-solid fa-cart-plus"></i> Tambah
                            @elseif($p->stok <= 0)
                            <i class="fa-solid fa-box-open"></i> Habis
                            @else
                            <i class="fa-solid fa-lock"></i> Admin
                            @endif
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="method-strip">
    <div class="method-inner">
        <div>
            <div class="section-label">Metode</div>
            <h3 style="font-size:1.2rem;font-weight:700;color:var(--primary);margin-top:.25rem;font-family:var(--font-display);">Item-Based Collaborative Filtering</h3>
            <p class="text-sm text-muted" style="margin-top:.5rem;line-height:1.6;">Sistem mempelajari hubungan antarproduk berdasarkan pola transaksi pelanggan, lalu merekomendasikan produk yang paling relevan.</p>
        </div>
        <div class="method-steps">
            @foreach (['Pengumpulan','Pembersihan','Analisis Pola','Hasil Rekomendasi'] as $i => $s)
            <div class="method-step">
                <div class="method-step-num">Tahap {{ $i+1 }}</div>
                <div class="method-step-label">{{ $s }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>

@push('scripts')
<script>
const stockMap = @json($stockMap);
const cartChannel = new BroadcastChannel('cart_stock_sync');

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
        } else {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = '';
            btn.innerHTML = '<i class="fa-solid fa-cart-plus"></i> Tambah';
        }
    });
}

async function addToCart(productId, qty = 1) {
    @if(Auth::check() && Auth::user()->role === 'admin')
    showToast('Admin tidak dapat menambah produk ke keranjang.');
    return;
    @endif
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
        cartChannel.postMessage({ type: 'stock_update', productId, newStok });
    } else {
        const currentStok = stockMap[productId] ?? 1;
        updateButtonsUI(productId, currentStok);
    }
}
</script>
@endpush

@endsection
