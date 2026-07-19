@extends('layouts.customer')

@section('title', 'Daftar — Toko Sinar Manis')

@push('styles')
<style>
    .register-layout {
        min-height: 100vh;
        display: flex; align-items: center; justify-content: center;
        background: #f8fafc; padding: 2rem 1rem;
    }
    .register-card {
        width: 100%; max-width: 600px;
        background: white; border: 1px solid var(--border);
        border-radius: var(--radius); padding: 2.5rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }
    .header-icon {
        width: 2.5rem; height: 2.5rem; background: var(--primary); color: white;
        border-radius: 0.5rem; display: grid; place-items: center;
        font-size: 1.1rem; flex-shrink: 0;
    }
    @media (max-width: 600px) {
        .register-card { padding: 1.5rem; }
        .grid-2 { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<div class="register-layout">
    <div class="register-card">
        <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 2rem;">
            <div class="header-icon"><i class="fa-solid fa-store"></i></div>
            <div>
                <h1 style="font-family: var(--font-display); font-weight: 700; font-size: 1.25rem; color: var(--primary);">
                    Buat akun pelanggan
                </h1>
                <p style="font-size: 0.8125rem; color: var(--muted-foreground); margin-top: 0.25rem;">
                    Daftar untuk berbelanja dan menerima rekomendasi produk personal.
                </p>
            </div>
        </div>

        @if ($errors->any())
            <div style="background: #fee2e2; color: #991b1b; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; margin-bottom: 1.5rem;">
                <i class="fa-solid fa-circle-exclamation" style="margin-right: 0.5rem;"></i> {{ $errors->first() }}
            </div>
        @endif
        @if (session('success'))
            <div style="background: #dcfce7; color: #166534; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; margin-bottom: 1.5rem;">
                <i class="fa-solid fa-circle-check" style="margin-right: 0.5rem;"></i> {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('register.post') }}">
            @csrf
            <div class="grid-2" style="margin-bottom: 1rem; gap: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="font-size: 0.8125rem;">Nama lengkap <span style="color:var(--destructive);">*</span></label>
                    <input type="text" name="nama" class="form-control" required placeholder="Andi Wijaya" value="{{ old('nama') }}">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="font-size: 0.8125rem;">Email <span style="color:var(--destructive);">*</span></label>
                    <input type="email" name="email" class="form-control" required placeholder="email@anda.com" value="{{ old('email') }}">
                </div>
            </div>

            <div class="grid-2" style="margin-bottom: 1rem; gap: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="font-size: 0.8125rem;">Nomor HP <span style="color:var(--destructive);">*</span></label>
                    <input type="text" name="no_hp" class="form-control" required placeholder="081xxxxxxxxx" value="{{ old('no_hp') }}">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="font-size: 0.8125rem;">Password <span style="color:var(--destructive);">*</span></label>
                    <input type="password" name="password" class="form-control" required placeholder="Minimal 6 karakter">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" style="font-size: 0.8125rem;">Konfirmasi password <span style="color:var(--destructive);">*</span></label>
                <input type="password" name="konfirmasi" class="form-control" required style="width: 50%;">
            </div>

            <div class="form-group">
                <label class="form-label" style="font-size: 0.8125rem;">Alamat <span style="color:var(--destructive);">*</span></label>
                <textarea name="alamat" class="form-control" rows="2" required placeholder="Jl. Melati No. 12, Bandung">{{ old('alamat') }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem; border-radius: 0.5rem;">Daftar Sekarang</button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem; font-size: 0.8125rem; color: var(--muted-foreground);">
            Sudah punya akun? <a href="{{ route('login') }}" style="color: var(--primary); text-decoration: underline; font-weight: 600;">Masuk di sini</a>
        </div>
    </div>
</div>
@endsection
