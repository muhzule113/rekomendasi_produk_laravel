@extends('layouts.customer')

@section('title', 'Tautan Verifikasi Tidak Valid — Toko Sinar Manis')

@section('content')
<section style="padding:4rem 1rem;min-height:60vh;background:#f8fafc;">
    <div class="container" style="max-width:680px;">
        <div class="card card-body" style="text-align:center;">
            <div style="width:4rem;height:4rem;margin:0 auto 1.25rem;background:#fee2e2;color:#b91c1c;border-radius:9999px;display:grid;place-items:center;font-size:1.5rem;">
                <i class="fa-solid fa-link-slash"></i>
            </div>
            <h1 style="font-size:1.75rem;font-weight:800;color:var(--primary);">Tautan Verifikasi Tidak Dapat Digunakan</h1>
            <p style="margin:1rem auto 0;max-width:34rem;color:var(--muted-foreground);line-height:1.7;">{{ $message }}</p>
            <p style="margin:.75rem auto 1.5rem;max-width:34rem;color:var(--muted-foreground);line-height:1.7;">
                Untuk keamanan, tautan hanya berlaku {{ $verificationMinutes ?? 60 }} menit dan hanya dapat digunakan oleh akun yang sesuai.
            </p>

            @auth
                @if(auth()->user()->role === 'pelanggan' && !auth()->user()->hasVerifiedEmail())
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-lg">Minta Tautan Baru</button>
                    </form>
                @else
                    <a href="{{ route('produk') }}" class="btn btn-primary btn-lg">Kembali ke Katalog</a>
                @endif
            @else
                <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Masuk untuk Meminta Tautan Baru</a>
            @endauth
        </div>
    </div>
</section>
@endsection
