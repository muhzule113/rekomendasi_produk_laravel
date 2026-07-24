@extends('layouts.customer')

@section('title', 'Keranjang Belanja — Toko Sinar Manis')

@section('content')

@php
$emojiMap = ['Sembako'=>'🛒','Makanan Instan'=>'🍜','Minuman'=>'🧃','Kebersihan'=>'🧼','Kebutuhan Mandi & Cuci'=>'🧴','default'=>'📦'];
function getEmojiCart(string $cat, array $map): string { return $map[$cat] ?? $map['default']; }
@endphp

<section class="page-header">
    <div class="container">
        <h1 style="font-size:2rem;font-weight:800;color:var(--primary);">Keranjang Belanja</h1>
        <p class="text-sm text-muted" style="margin-top:.25rem;">Periksa kembali pesanan Anda sebelum melanjutkan ke pembayaran.</p>
    </div>
</section>

<section style="padding: 2rem 0 4rem;">
    <div class="container">
        @if(empty($cartItems) || count($cartItems) === 0)
        <div class="card card-dashed mt-8">
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-bag-shopping"></i></div>
                <div class="empty-title">Keranjang masih kosong</div>
                <p class="empty-desc">Yuk mulai belanja produk kebutuhan harian Anda di katalog Sinar Manis.</p>
                <a href="{{ route('produk') }}" class="btn btn-primary btn-md mt-4">Mulai Belanja</a>
            </div>
        </div>
        @else
        <div class="grid-cols-2-1 mt-6">
            <!-- Left: Cart Items -->
            <div class="card">
                <div class="divide-y">
                    @foreach($cartItems as $item)
                    <div class="cart-item">
                        <div class="cart-item-top">
                            <div class="cart-item-emoji">
                                @if(!empty($item['foto']))
                                    <img src="{{ asset('storage/'.$item['foto']) }}" alt="{{ $item['nama_product'] }}">
                                @else
                                    {{ getEmojiCart($item['nama_category'], $emojiMap) }}
                                @endif
                            </div>
                            <div class="cart-item-info">
                                <div class="cart-item-name">{{ $item['nama_product'] }}</div>
                                <div class="cart-item-meta">{{ $item['nama_category'] }} &middot; {{ \App\Helpers\Helpers::formatRupiah($item['harga']) }}</div>
                            </div>
                            <button onclick="removeFromCart({{ $item['id_product'] }})" class="cart-item-delete" title="Hapus">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                        <div class="cart-item-bottom">
                            <div class="cart-qty-control">
                                <button onclick="updateQty({{ $item['id_product'] }}, {{ $item['qty'] }}, -1)" class="btn btn-outline cart-qty-btn">-</button>
                                <input type="text" class="form-control cart-qty-input" value="{{ $item['qty'] }}" readonly>
                                <button onclick="updateQty({{ $item['id_product'] }}, {{ $item['qty'] }}, 1)" class="btn btn-outline cart-qty-btn">+</button>
                            </div>
                            <div class="cart-item-subtotal">{{ \App\Helpers\Helpers::formatRupiah($item['subtotal']) }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Right: Summary -->
            <div>
                <div class="card card-body" style="position:sticky;top:5.5rem;">
                    <div style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--primary);">Ringkasan Belanja</div>
                    <div style="display:flex;justify-content:space-between;margin-top:1rem;font-size:.875rem;">
                        <span class="text-muted">Subtotal</span>
                        <span style="font-weight:500;">{{ \App\Helpers\Helpers::formatRupiah($total) }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-top:.25rem;font-size:.875rem;">
                        <span class="text-muted">Ongkos kirim</span>
                        <span style="font-weight:500;">Gratis</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);">
                        <span style="font-weight:600;">Total</span>
                        <span style="font-family:var(--font-display);font-size:1.25rem;font-weight:700;color:var(--primary);">{{ \App\Helpers\Helpers::formatRupiah($total) }}</span>
                    </div>
                    <a href="{{ route('checkout') }}" class="btn btn-primary btn-lg btn-block mt-6">
                        Lanjut Checkout <i class="fa-solid fa-chevron-right" style="font-size:.75rem;margin-left:.25rem;"></i>
                    </a>
                </div>
            </div>
        </div>
        @endif
    </div>
</section>

@push('scripts')
<script>
async function updateQty(productId, currentQty, change) {
    let newQty = currentQty + change;
    if (newQty < 1) newQty = 1;

    try {
        const res = await fetch('/api/cart', {
            method:'PUT', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({product_id: productId, qty: newQty})
        });
        const data = await res.json();
        if (!data.status) {
            alert(data.message);
        } else {
            location.reload();
        }
    } catch (e) {
        alert("Gagal mengubah jumlah");
    }
}
async function removeFromCart(productId) {
    if (confirm("Yakin ingin menghapus produk ini dari keranjang?")) {
        try {
            await fetch('/api/cart', {
                method:'DELETE', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({product_id: productId})
            });
            location.reload();
        } catch (e) {
            alert("Gagal menghapus produk");
        }
    }
}
</script>
@endpush

@endsection
