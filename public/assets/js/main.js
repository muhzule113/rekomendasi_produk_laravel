function showToast(message) {
    const container = document.getElementById('toast-container') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `<i class="fa-solid fa-circle-info"></i> <span>${message}</span>`;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
    return container;
}

function updateCartBadge(count) {
    const badges = document.querySelectorAll('.cart-badge');
    badges.forEach(badge => {
        badge.innerText = count;
        if(count > 0) {
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    });
}

function formatRupiah(number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
}

document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const drawer = document.getElementById('mobileDrawer');
    const overlay = document.getElementById('mobileOverlay');
    const closeBtn = document.getElementById('mobileDrawerClose');

    function openDrawer() {
        document.body.classList.add('mobile-menu-open');
        if (menuToggle) {
            const icon = menuToggle.querySelector('i');
            if (icon) icon.className = 'fa-solid fa-xmark';
        }
    }

    function closeDrawer() {
        document.body.classList.remove('mobile-menu-open');
        if (menuToggle) {
            const icon = menuToggle.querySelector('i');
            if (icon) icon.className = 'fa-solid fa-bars';
        }
    }

    if (menuToggle) menuToggle.addEventListener('click', () => {
        document.body.classList.contains('mobile-menu-open') ? closeDrawer() : openDrawer();
    });

    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (overlay) overlay.addEventListener('click', closeDrawer);

    // Prevent background scroll on touch devices
    document.addEventListener('touchmove', (e) => {
        if (document.body.classList.contains('mobile-menu-open')) {
            // Allow scrolling inside drawer if content overflows
            if (drawer && drawer.contains(e.target)) return;
            e.preventDefault();
        }
    }, { passive: false });
});

/* ===================== LOGOUT MODAL ===================== */
function showLogoutModal() {
    const modal = document.getElementById('logoutModal');
    if (modal) {
        modal.classList.add('active');
    }
}

function hideLogoutModal() {
    const modal = document.getElementById('logoutModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Close modal on backdrop click
document.addEventListener('click', (e) => {
    if (e.target.id === 'logoutModal') hideLogoutModal();
});
