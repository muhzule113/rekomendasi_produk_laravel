<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin — Toko Sinar Manis')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}?v={{ time() }}">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    @stack('styles')
</head>
<body>

<div class="admin-sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="admin-layout">
    @include('admin.sidebar')
    <div class="admin-main">
        <div class="admin-topbar">
            <button class="admin-topbar-toggle" onclick="toggleSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div style="flex:1;"></div>
            <a href="{{ route('home') }}" class="btn btn-outline btn-sm" target="_blank">
                <i class="fa-solid fa-arrow-up-right-from-square" style="margin-right:0.25rem;"></i> Lihat Toko
            </a>
        </div>
        <div class="admin-content">
            @yield('content')
        </div>
    </div>
</div>

<div class="confirm-dialog-overlay" id="confirmDialog" style="display:none;">
    <div class="confirm-dialog-card">
        <div class="confirm-dialog-body">
            <div class="confirm-dialog-icon" id="confirmDialogIcon"></div>
            <h3 class="confirm-dialog-title" id="confirmDialogTitle"></h3>
            <p class="confirm-dialog-desc" id="confirmDialogDesc"></p>
        </div>
        <div class="confirm-dialog-actions">
            <button class="confirm-dialog-btn confirm-btn-cancel" onclick="hideConfirmDialog()">Batal</button>
            <button class="confirm-dialog-btn confirm-btn-confirm" id="confirmDialogBtn">Ya, Lanjutkan</button>
        </div>
    </div>
</div>

<div class="loading-overlay" id="loadingOverlay" style="display:none;">
    <div class="loading-spinner"></div>
</div>

<div id="toast-container"></div>

<script src="{{ asset('assets/js/main.js') }}?v={{ time() }}"></script>
<script src="{{ asset('admin/js/admin.js') }}?v={{ time() }}"></script>
@stack('scripts')
</body>
</html>
