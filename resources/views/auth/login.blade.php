@extends('layouts.customer')

@section('title', 'Masuk — Toko Sinar Manis')

@push('styles')
<style>
    .login-layout { display: flex; min-height: 100vh; }
    .login-left {
        flex: 1; background: var(--primary);
        color: white; padding: 4rem;
        display: flex; flex-direction: column; justify-content: center;
    }
    .login-right {
        flex: 1; background: #f8fafc;
        display: flex; align-items: center; justify-content: center;
        padding: 2rem;
    }
    .login-card { width: 100%; max-width: 440px; background: white; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 2.5rem 2rem; }
    .role-tabs { display: flex; background: var(--secondary); border-radius: 0.5rem; padding: 0.25rem; margin-bottom: 1.5rem; }
    .role-tab { flex: 1; text-align: center; font-size: 0.8125rem; font-weight: 600; padding: 0.5rem; border-radius: 0.375rem; cursor: pointer; color: var(--muted-foreground); transition: all 0.2s; }
    .role-tab.active { background: white; color: var(--primary); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    @media (max-width: 900px) {
        .login-layout { flex-direction: column; }
        .login-left { padding: 3rem 1.5rem; }
    }
</style>
@endpush

@section('content')
<div class="login-layout">
    <div class="login-left">
        <div style="max-width: 480px; margin: 0 auto;">
            <div style="width: 3rem; height: 3rem; background: var(--gold); color: var(--primary); display: grid; place-items: center; border-radius: 0.75rem; font-size: 1.25rem; margin-bottom: 2rem;">
                <i class="fa-solid fa-store"></i>
            </div>
            <h1 style="font-family: var(--font-display); font-size: 2.25rem; font-weight: 800; line-height: 1.2; margin-bottom: 1rem;">
                Selamat datang kembali di Toko Sinar Manis
            </h1>
            <p style="color: rgba(255,255,255,0.7); font-size: 0.9375rem; line-height: 1.6;">
                Masuk untuk melihat rekomendasi produk yang dipersonalisasi berdasarkan riwayat transaksi Anda.
            </p>
        </div>
    </div>

    <div class="login-right">
        <div class="login-card">
            <h2 style="font-family: var(--font-display); font-weight: 700; color: var(--primary); font-size: 1.5rem; margin-bottom: 0.25rem;">Masuk</h2>
            <p style="color: var(--muted-foreground); font-size: 0.875rem; margin-bottom: 2rem;">Pilih peran Anda lalu masukkan kredensial.</p>

            @if ($errors->any())
                <div style="background: #fee2e2; color: #991b1b; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; margin-bottom: 1.5rem;">
                    <i class="fa-solid fa-circle-exclamation" style="margin-right: 0.5rem;"></i> {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf
                <div class="role-tabs">
                    <div class="role-tab active" onclick="setRole('pelanggan', this)">Pelanggan</div>
                    <div class="role-tab" onclick="setRole('admin', this)">Admin</div>
                </div>
                <input type="hidden" name="role" id="input-role" value="{{ old('role', 'pelanggan') }}">
                
                <p id="role-desc" style="font-size: 0.75rem; color: var(--muted-foreground); margin-bottom: 1rem;">
                    Login untuk pelanggan Toko Sinar Manis.
                </p>

                <div class="form-group">
                    <label class="form-label" style="font-size: 0.8125rem;">Email</label>
                    <input type="email" name="email" class="form-control" required placeholder="email@anda.com" value="{{ old('email') }}">
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="font-size: 0.8125rem;">Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>
                
                <button type="submit" id="submit-btn" class="btn btn-primary btn-block btn-lg" style="height: 2.75rem;">Masuk sebagai Pelanggan</button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="{{ route('home') }}" style="font-size: 0.75rem; color: var(--muted-foreground); transition: color 0.2s;">&larr; Kembali ke beranda</a>
            </div>
        </div>
    </div>
</div>

<script>
function setRole(role, el) {
    document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('input-role').value = role;
    if (role === 'admin') {
        document.getElementById('role-desc').textContent = 'Login khusus pemilik toko / pengelola sistem.';
        document.getElementById('submit-btn').textContent = 'Masuk sebagai Admin';
    } else {
        document.getElementById('role-desc').textContent = 'Login untuk pelanggan Toko Sinar Manis.';
        document.getElementById('submit-btn').textContent = 'Masuk sebagai Pelanggan';
    }
}
@if(old('role') === 'admin')
setRole('admin', document.querySelectorAll('.role-tab')[1]);
@endif
</script>
@endsection
