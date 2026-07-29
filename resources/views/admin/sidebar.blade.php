<div class="admin-sidebar">
    <div class="sidebar-top">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-logo">
            <div class="sidebar-logo-icon-wrap">
                <img src="{{ asset('assets/images/logosinarmanis.png') }}" alt="Toko Sinar Manis">
            </div>
            <span class="sidebar-logo-text">Sinar Manis</span>
        </a>
        <div class="sidebar-meta">Admin Panel</div>
    <button class="sidebar-close-btn" onclick="closeSidebar()" aria-label="Tutup sidebar">
        <i class="fa-solid fa-times"></i>
    </button>
    </div>

    <nav class="sidebar-menu">
        @php $active = basename(Request::path()); @endphp
        <a href="{{ route('admin.dashboard') }}" class="{{ $active === 'admin' ? 'active' : '' }}">
            <i class="fa-solid fa-gauge"></i> Dashboard
        </a>
        <a href="{{ route('admin.produk') }}" class="{{ Request::is('admin/produk*') ? 'active' : '' }}">
            <i class="fa-solid fa-box"></i> Kelola Produk
        </a>
        <a href="{{ route('admin.pelanggan') }}" class="{{ Request::is('admin/pelanggan*') ? 'active' : '' }}">
            <i class="fa-solid fa-users"></i> Data Pelanggan
        </a>
        <a href="{{ route('admin.transaksi') }}" class="{{ Request::is('admin/transaksi*') ? 'active' : '' }}">
            <i class="fa-solid fa-receipt"></i> Kelola Transaksi
        </a>
        <a href="{{ route('admin.analisis') }}" class="{{ Request::is('admin/analisis*') ? 'active' : '' }}">
            <i class="fa-solid fa-chart-line"></i> Analisis Rekomendasi
        </a>
        <a href="{{ route('admin.reviews') }}" class="{{ Request::is('admin/reviews*') ? 'active' : '' }}">
            <i class="fa-solid fa-star"></i> Ulasan & Rating
        </a>
        <a href="{{ route('admin.upload') }}" class="{{ Request::is('admin/upload*') ? 'active' : '' }}">
            <i class="fa-solid fa-upload"></i> Upload Data
        </a>
        <a href="{{ route('admin.upload-history') }}" class="{{ Request::is('admin/upload-history*') ? 'active' : '' }}">
            <i class="fa-solid fa-clock-rotate-left"></i> Riwayat Upload
        </a>
        <a href="{{ route('admin.laporan') }}" class="{{ Request::is('admin/laporan*') ? 'active' : '' }}">
            <i class="fa-solid fa-file-lines"></i> Laporan
        </a>
    </nav>

    <div class="sidebar-footer">
        <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none;">
            @csrf
        </form>
        <button type="button" class="sidebar-logout-btn" onclick="confirmLogout()">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
        </button>
    </div>
</div>
