@extends('layouts.customer')

@section('title', 'Riwayat Transaksi — Toko Sinar Manis')

@section('content')

@php
$emojiMap = ['Sembako'=>'🛒','Makanan Instan'=>'🍜','Minuman'=>'🧃','Kebersihan'=>'🧼','Kebutuhan Mandi & Cuci'=>'🧴','default'=>'📦'];
function getEmojiRiwayat(string $cat, array $map): string { return $map[$cat] ?? $map['default']; }
@endphp

<section class="page-header">
    <div class="container">
        <h1 style="font-size:2rem;font-weight:800;color:var(--primary);">Riwayat Transaksi</h1>
        <p class="text-sm text-muted" style="margin-top:.25rem;">Lacak status pesanan dan lihat riwayat belanja Anda.</p>
    </div>
</section>

<section style="padding: 2rem 0 4rem;">
    <div class="container">
        @if(empty($transactions) || count($transactions) === 0)
        <div class="card card-dashed mt-8">
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div class="empty-title">Belum ada transaksi</div>
                <p class="empty-desc">Anda belum pernah melakukan transaksi di Toko Sinar Manis.</p>
                <a href="{{ route('produk') }}" class="btn btn-primary btn-md mt-4">Mulai Belanja</a>
            </div>
        </div>
        @else
        <div style="display:flex;flex-direction:column;gap:1.5rem;margin-top:2rem;">
            @foreach($transactions as $t)
                @php
                    $badgeClassPesanan = match($t->status_pesanan) {
                        'Diproses', 'Dikirim' => 'badge-gold',
                        'Selesai' => 'badge-green',
                        'Dibatalkan' => 'badge-red',
                        'Menunggu Pembayaran' => 'badge-gray',
                        default => 'badge-gray',
                    };

                    $badgeClassPembayaran = match($t->status_pembayaran) {
                        'Dibayar' => 'badge-green',
                        'Pending' => 'badge-gold',
                        'Gagal', 'Expired', 'Dibatalkan' => 'badge-red',
                        'Refund' => 'badge-red',
                        default => 'badge-gray',
                    };
                @endphp
            <div class="card">
                <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;background:var(--secondary);">
                    <div>
                        <div style="font-weight:700;color:var(--primary);font-family:var(--font-display);">#TRX-{{ str_pad($t->id_transaction, 5, '0', STR_PAD_LEFT) }}</div>
                        <div style="font-size:.75rem;color:var(--muted-foreground);margin-top:.15rem;">{{ \Carbon\Carbon::parse($t->tanggal)->format('d M Y, H:i') }}</div>
                    </div>
                    <div style="display:flex; gap: 0.5rem; align-items:center;">
                        <div style="text-align:right;">
                            <div style="font-size:0.7rem; color:var(--muted-foreground);">Pembayaran</div>
                            <span class="badge {{ $badgeClassPembayaran }}" style="text-transform:uppercase; font-size:0.7rem; padding: 0.15rem 0.4rem;">{{ $t->status_pembayaran }}</span>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.7rem; color:var(--muted-foreground);">Pesanan</div>
                            <span class="badge {{ $badgeClassPesanan }}" style="text-transform:uppercase; font-size:0.7rem; padding: 0.15rem 0.4rem;">{{ $t->status_pesanan }}</span>
                        </div>
                    </div>
                </div>
                <div class="divide-y">
                    @foreach($t->items as $d)
                    <div style="padding:1rem 1.5rem;display:flex;align-items:center;gap:1rem;">
                        <div class="cart-item-emoji">
                            @if(!empty($d->foto))
                                <img src="{{ asset('storage/'.$d->foto) }}" alt="{{ $d->nama_product }}">
                            @else
                                {{ getEmojiRiwayat($d->nama_category, $emojiMap) }}
                            @endif
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="font-weight:600;color:var(--primary);">{{ $d->nama_product }}</div>
                            <div style="font-size:.75rem;color:var(--muted-foreground);">{{ $d->qty }} barang x {{ \App\Helpers\Helpers::formatRupiah($d->harga) }}</div>
                        </div>
                        <div style="text-align:right;font-weight:600;color:var(--primary);">
                            {{ \App\Helpers\Helpers::formatRupiah($d->qty * $d->harga) }}
                        </div>
                    </div>
                    @endforeach
                </div>
                <div style="padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;background:rgba(241,245,249,.3);">
                    <div>
                        <div style="font-size:.875rem;">
                            <span class="text-muted">Metode Pembayaran:</span>
                            <span style="font-weight:600;color:var(--primary);">{{ $t->metode_pembayaran }}</span>
                        </div>
                        @if($t->metode_pembayaran === 'Midtrans' && $t->status_pembayaran === 'Pending')
                            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.5rem;">
                                @if(!empty($t->snap_token))
                                <button class="btn btn-sm btn-primary" onclick="payMidtrans('{{ $t->snap_token }}', '{{ $t->midtrans_order_id }}')">Bayar Sekarang</button>
                                @endif
                                @if(!empty($t->midtrans_order_id))
                                <button class="btn btn-sm btn-outline" onclick="verifyAndReload('{{ $t->midtrans_order_id }}')">Cek Status</button>
                                @endif
                            </div>
                        @elseif(in_array($t->status_pembayaran, ['Gagal', 'Expired']))
                            <button class="btn btn-sm btn-outline mt-2" onclick="window.location.href='{{ route('keranjang') }}'">Checkout Ulang</button>
                        @endif
                    </div>
                    <div style="text-align:right;">
                        <span class="text-muted" style="font-size:.875rem;margin-right:.5rem;">Total Belanja</span>
                        <span style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--primary);">{{ \App\Helpers\Helpers::formatRupiah($t->total) }}</span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>

@if($clientKey)
@push('scripts')
<script src="{{ $snapJsUrl ?? \App\Services\MidtransPaymentService::snapJsUrl() }}" data-client-key="{{ $clientKey }}"></script>
<script>
function verifyAndReload(orderId) {
    fetch('{{ route('verify.payment') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({order_id: orderId})
    }).finally(function() {
        window.location.href = '{{ route('riwayat') }}?order_id=' + encodeURIComponent(orderId);
    });
}
function payMidtrans(token, orderId) {
    if(!token) {
        alert("Token tidak ditemukan.");
        return;
    }
    snap.pay(token, {
        onSuccess: function(){ verifyAndReload(orderId); },
        onPending: function(){ verifyAndReload(orderId); },
        onError: function(){ verifyAndReload(orderId); },
        onClose: function(){ verifyAndReload(orderId); }
    });
}
</script>
@endpush
@endif

@endsection
