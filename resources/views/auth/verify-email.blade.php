@extends('layouts.customer')

@section('title', 'Verifikasi Email — Toko Sinar Manis')

@section('content')
<section style="padding:4rem 1rem;min-height:60vh;background:#f8fafc;">
    <div class="container" style="max-width:680px;">
        <div class="card card-body" style="text-align:center;">
            <div style="width:4rem;height:4rem;margin:0 auto 1.25rem;background:#fef3c7;color:#b45309;border-radius:9999px;display:grid;place-items:center;font-size:1.5rem;">
                <i class="fa-solid fa-envelope-circle-check"></i>
            </div>
            <h1 style="font-size:1.75rem;font-weight:800;color:var(--primary);">Verifikasi Email Anda</h1>
            <p style="margin:1rem auto 0;max-width:34rem;color:var(--muted-foreground);line-height:1.7;">
                Kami sudah mengirim tautan verifikasi ke <strong>{{ $email }}</strong>.
                Buka tautan tersebut untuk menjadi Pelanggan Terverifikasi Toko Sinar Manis.
            </p>

            @if(session('status') || session('error'))
                <div style="margin-top:1.25rem;background:{{ session('error') ? '#fee2e2' : '#dcfce7' }};color:{{ session('error') ? '#991b1b' : '#166534' }};padding:.75rem 1rem;border-radius:.5rem;text-align:left;">
                    {{ session('error') ?? session('status') }}
                </div>
            @endif

            <div style="margin:1.5rem 0;text-align:left;background:#eff6ff;border:1px solid #bfdbfe;border-radius:.75rem;padding:1rem;color:#1e3a8a;line-height:1.6;font-size:.9rem;">
                <strong>Tautan berlaku {{ $verificationMinutes }} menit.</strong>
                Sebelum email terverifikasi, Anda tetap dapat melihat katalog, detail produk, keranjang berbasis sesi, dan Rekomendasi Publik.
                Checkout, riwayat transaksi, ulasan, verifikasi pembayaran, dan Rekomendasi Personal tersedia setelah verifikasi.
            </div>

            <div style="margin-top:1.5rem;display:flex;justify-content:center;gap:.75rem;flex-wrap:wrap;">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-lg">Kirim Ulang Email Verifikasi</button>
                </form>
                <form method="GET" action="{{ route('verification.check') }}">
                    <button type="submit" class="btn btn-outline btn-lg">
                        <i class="fa-solid fa-circle-check"></i> Sudah Verifikasi
                    </button>
                </form>
            </div>

            <div style="margin-top:1rem;display:flex;justify-content:center;gap:1rem;flex-wrap:wrap;font-size:.875rem;">
                <a href="{{ route('produk') }}" style="color:var(--primary);">Lanjut melihat katalog</a>
                <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                    @csrf
                    <button type="submit" style="border:0;background:none;color:var(--muted-foreground);cursor:pointer;">Keluar</button>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
