<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Toko Sinar Manis — Rekomendasi Cerdas')</title>
    <link rel="icon" href="{{ asset('assets/images/logosinarmanis.png') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ time() }}">
    @stack('styles')
</head>
<body>

<nav class="navbar">
    <div class="navbar-inner">
        <a href="{{ route('home') }}" class="navbar-logo">
            <div class="logo-icon">
                <img src="{{ asset('assets/images/logosinarmanis.png') }}" alt="Toko Sinar Manis">
            </div>
            <div>
                <div class="logo-text-main">Toko Sinar Manis</div>
                <div class="logo-text-sub">Rekomendasi Cerdas</div>
            </div>
        </a>

        <nav class="navbar-nav desktop-only">
            <a href="{{ route('home') }}" class="nav-link {{ Request::routeIs('home') ? 'active' : '' }}">Beranda</a>
            <a href="{{ route('produk') }}" class="nav-link {{ Request::routeIs('produk') ? 'active' : '' }}">Katalog</a>
            @if(Auth::check() && Auth::user()->role === 'pelanggan')
                <a href="{{ route('rekomendasi') }}" class="nav-link {{ Request::routeIs('rekomendasi') ? 'active' : '' }}">Rekomendasi</a>
                <a href="{{ route('riwayat') }}" class="nav-link {{ Request::routeIs('riwayat') ? 'active' : '' }}">Riwayat</a>
            @endif
        </nav>

        <div class="navbar-actions desktop-only">
            @if(!Auth::check() || Auth::user()->role === 'pelanggan')
            <a href="{{ route('keranjang') }}" class="cart-btn" style="text-decoration:none;">
                <i class="fa-solid fa-cart-shopping" style="font-size:1rem;"></i>
                @php
                    $cart_count = 0;
                    $cart = session('cart', []);
                    foreach ($cart as $v) {
                        $cart_count += is_array($v) ? ($v['qty'] ?? 0) : (int)$v;
                    }
                @endphp
                <span class="cart-badge" style="{{ $cart_count > 0 ? '' : 'display:none;' }}">{{ $cart_count }}</span>
            </a>
            @endif
            @if(Auth::check())
                @if(Auth::user()->role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline btn-sm">Dashboard</a>
                @endif
                <button onclick="showLogoutModal()" class="btn btn-ghost btn-sm" style="color:var(--muted-foreground); background:none; border:none; padding:0;">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </button>
            @else
                <a href="{{ route('login') }}" class="btn btn-outline btn-sm">Masuk</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Daftar</a>
            @endif
        </div>

        <button class="mobile-menu-toggle" aria-label="Toggle Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
</nav>

<div class="mobile-overlay" id="mobileOverlay"></div>

<div class="mobile-drawer" id="mobileDrawer">
    <div class="mobile-drawer-header">
        <a href="{{ route('home') }}" class="navbar-logo">
            <div class="logo-icon">
                <img src="{{ asset('assets/images/logosinarmanis.png') }}" alt="Toko Sinar Manis">
            </div>
            <div><div class="logo-text-main">Toko Sinar Manis</div></div>
        </a>
        <button class="mobile-drawer-close" id="mobileDrawerClose" aria-label="Tutup Menu">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <div class="mobile-drawer-divider"></div>

    <nav class="mobile-drawer-nav">
        <a href="{{ route('home') }}" class="mobile-nav-link {{ Request::routeIs('home') ? 'active' : '' }}">
            <i class="fa-solid fa-house"></i> Beranda
        </a>
        <a href="{{ route('produk') }}" class="mobile-nav-link {{ Request::routeIs('produk') ? 'active' : '' }}">
            <i class="fa-solid fa-box-open"></i> Katalog
        </a>
        @if(Auth::check() && Auth::user()->role === 'pelanggan')
        <a href="{{ route('rekomendasi') }}" class="mobile-nav-link {{ Request::routeIs('rekomendasi') ? 'active' : '' }}">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Rekomendasi
        </a>
        <a href="{{ route('riwayat') }}" class="mobile-nav-link {{ Request::routeIs('riwayat') ? 'active' : '' }}">
            <i class="fa-solid fa-clock-rotate-left"></i> Riwayat
        </a>
        @endif
        @if(!Auth::check() || Auth::user()->role === 'pelanggan')
        <a href="{{ route('keranjang') }}" class="mobile-nav-link {{ Request::routeIs('keranjang') ? 'active' : '' }}">
            <i class="fa-solid fa-cart-shopping"></i> Keranjang
            @if($cart_count > 0)
            <span class="mobile-cart-count">{{ $cart_count }}</span>
            @endif
        </a>
        @endif
    </nav>

    <div class="mobile-drawer-divider"></div>

    <div class="mobile-drawer-actions">
        @if(Auth::check())
            @if(Auth::user()->role === 'admin')
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline btn-block">
                    <i class="fa-solid fa-gauge"></i> Dashboard
                </a>
            @endif
            <button onclick="showLogoutModal()" class="btn btn-outline btn-block" style="width:100%;color:var(--destructive);border-color:var(--destructive);">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
            </button>
        @else
            <a href="{{ route('login') }}" class="btn btn-outline btn-block">
                <i class="fa-solid fa-right-to-bracket"></i> Masuk
            </a>
            <a href="{{ route('register') }}" class="btn btn-primary btn-block">
                <i class="fa-solid fa-user-plus"></i> Daftar
            </a>
        @endif
    </div>
</div>

<div class="logout-modal-overlay" id="logoutModal">
    <div class="logout-modal-card">
        <div class="logout-modal-body">
            <div class="logout-modal-icon"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
            <h3 class="logout-modal-title">Keluar dari akun?</h3>
            <p class="logout-modal-desc">Anda akan keluar dari sesi saat ini. Yakin ingin melanjutkan?</p>
        </div>
        <div class="logout-modal-actions">
            <button class="logout-modal-btn logout-btn-cancel" onclick="hideLogoutModal()">Batal</button>
            <form method="POST" action="{{ route('logout') }}" style="flex:1;">
                @csrf
                <button type="submit" class="logout-modal-btn logout-btn-confirm">Ya, Keluar</button>
            </form>
        </div>
    </div>
</div>

@if(session('success') || session('status') || session('error'))
    <div class="container" style="padding-top:1rem;">
        @if(session('success') || session('status'))
            <div style="background:#dcfce7;color:#166534;padding:.75rem 1rem;border-radius:.5rem;">
                {{ session('success') ?? session('status') }}
            </div>
        @else
            <div style="background:#fee2e2;color:#991b1b;padding:.75rem 1rem;border-radius:.5rem;">
                {{ session('error') }}
            </div>
        @endif
    </div>
@endif

<div id="toast-container"></div>

@yield('content')
<footer class="footer">
    <div class="footer-inner">
        <div>
            <div class="footer-brand">
                <div class="logo-icon" style="width:2.25rem;height:2.25rem;">
                    <img src="{{ asset('assets/images/logosinarmanis.png') }}" alt="Toko Sinar Manis">
                </div>
                <span class="footer-brand-name">Toko Sinar Manis</span>
            </div>
            <p class="footer-desc">
                Sistem toko berbasis web dengan modul rekomendasi produk berbasis <a href="#" style="color:var(--primary);font-weight:500;">analisis data transaksi</a> pelanggan.
            </p>
        </div>
        <div>
            <div class="footer-heading">Navigasi</div>
            <div class="footer-links">
                <a href="{{ route('home') }}">Beranda</a>
                <a href="{{ route('produk') }}">Katalog Produk</a>
                <a href="{{ route('rekomendasi') }}">Rekomendasi</a>
                <a href="{{ route('riwayat') }}">Riwayat Transaksi</a>
            </div>
        </div>
        <div>
            <div class="footer-heading">Skripsi</div>
            <p class="footer-desc">
                Pengembangan Sistem Rekomendasi Produk Berbasis Analisis Big Data terhadap Transaksi Pelanggan &mdash; Studi Kasus Toko Sinar Manis.
            </p>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; {{ date('Y') }} Toko Sinar Manis &middot; Item-Based Collaborative Filtering
    </div>
</footer>

<script src="{{ asset('assets/js/main.js') }}?v={{ time() }}"></script>
@stack('scripts')
</body>
</html>
