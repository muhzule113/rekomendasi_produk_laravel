@extends('layouts.customer')

@section('title', 'Checkout — Toko Sinar Manis')

@section('content')

<section class="page-header">
    <div class="container">
        <h1 style="font-size:2rem;font-weight:800;color:var(--primary);">Checkout</h1>
        <p class="text-sm text-muted" style="margin-top:.25rem;">Selesaikan pesanan Anda dengan aman.</p>
    </div>
</section>

<section style="padding: 2rem 0 4rem;">
    <div class="container">
        @if(session('error'))
            <div style="background:var(--destructive);color:white;padding:1rem;border-radius:var(--radius);margin-bottom:1.5rem;">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('checkout.store') }}" class="grid-cols-2-1">
            @csrf

            <!-- Left: Items & Shipping Info -->
            <div style="display:flex;flex-direction:column;gap:1.5rem;">
                <div class="card card-body">
                    <h3 style="font-size:1.1rem;font-weight:700;color:var(--primary);margin-bottom:1rem;font-family:var(--font-display);">Informasi Pengiriman</h3>
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" value="{{ $user->nama }}" readonly style="background:var(--secondary);color:var(--muted-foreground);">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="{{ $user->email }}" readonly style="background:var(--secondary);color:var(--muted-foreground);">
                    </div>
                </div>

                <div class="card">
                    <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--border);">
                        <h3 style="font-size:1.1rem;font-weight:700;color:var(--primary);font-family:var(--font-display);">Pesanan Anda</h3>
                    </div>
                    <div class="divide-y">
                        @foreach($cartItems as $item)
                        <div style="padding:1rem 1.5rem;display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <div style="font-weight:600;color:var(--primary);">{{ $item['product']->nama_product }}</div>
                                <div style="font-size:.75rem;color:var(--muted-foreground);">{{ $item['qty'] }} x {{ \App\Helpers\Helpers::formatRupiah($item['product']->harga) }}</div>
                            </div>
                            <div style="font-weight:600;color:var(--primary);">{{ \App\Helpers\Helpers::formatRupiah($item['subtotal']) }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Right: Payment & Summary -->
            <div>
                <div class="card card-body" style="position:sticky;top:5.5rem;">
                    <h3 style="font-size:1.1rem;font-weight:700;color:var(--primary);margin-bottom:1rem;font-family:var(--font-display);">Metode Pembayaran</h3>
                    <div class="form-group">
                        <select name="metode_pembayaran" class="form-control" required>
                            <option value="">Pilih Metode</option>
                            <option value="Tunai">Tunai (Bayar di Toko)</option>
                            @if($midtransEnabled)
                            <option value="Midtrans">Online Payment (Midtrans)</option>
                            @endif
                        </select>
                    </div>

                    <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border);">
                        <div style="display:flex;justify-content:space-between;margin-bottom:.5rem;font-size:.875rem;">
                            <span class="text-muted">Subtotal</span>
                            <span style="font-weight:500;">{{ \App\Helpers\Helpers::formatRupiah($subtotal) }}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:.875rem;">
                            <span class="text-muted">Ongkos kirim</span>
                            <span style="font-weight:500;">Gratis</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);">
                            <span style="font-weight:600;">Total</span>
                            <span style="font-family:var(--font-display);font-size:1.25rem;font-weight:700;color:var(--primary);">{{ \App\Helpers\Helpers::formatRupiah($subtotal) }}</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg btn-block mt-6">
                        Buat Pesanan <i class="fa-solid fa-check" style="font-size:.75rem;margin-left:.25rem;"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>

@if($midtransEnabled)
@push('scripts')
<script src="{{ \App\Services\MidtransPaymentService::snapJsUrl() }}" data-client-key="{{ $midtransClientKey }}"></script>
@endpush
@endif

@endsection
