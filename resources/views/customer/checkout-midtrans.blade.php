@extends('layouts.customer')

@section('title', 'Pembayaran — Toko Sinar Manis')

@section('content')
<div class="container" style="max-width:600px;margin:0 auto;padding:3rem 0;">
    <div class="card card-body text-center">
        <div style="font-size:3rem;margin-bottom:1rem;">💳</div>
        <h2 style="color:var(--primary);margin-bottom:.5rem;">Pembayaran Online</h2>
        <p style="color:var(--muted-foreground);margin-bottom:1.5rem;">
            Order <strong>#{{ $transaction->kode_transaksi }}</strong><br>
            Total: <strong>{{ \App\Helpers\Helpers::formatRupiah($transaction->total) }}</strong>
        </p>
        <p id="pay-status" style="color:var(--muted-foreground);font-size:.875rem;margin-bottom:1rem;">
            Membuka jendela pembayaran...
        </p>
        <button id="pay-button" class="btn btn-primary btn-lg btn-block mt-4">
            <i class="fa-solid fa-credit-card"></i> Bayar Sekarang
        </button>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ \App\Services\MidtransPaymentService::snapJsUrl() }}" data-client-key="{{ env('MIDTRANS_CLIENT_KEY') }}"></script>
<script>
(function() {
    var orderId = @json($transaction->kode_transaksi);
    var snapToken = @json($snapToken);
    var riwayatUrl = @json(route('riwayat'));
    var verifyUrl = @json(route('verify.payment'));
    var csrfToken = @json(csrf_token());
    var paying = false;
    var finishing = false;

    function setStatus(text) {
        var el = document.getElementById('pay-status');
        if (el) el.textContent = text;
    }

    function redirectToRiwayat(status) {
        var qs = '?order_id=' + encodeURIComponent(orderId);
        if (status) qs += '&status=' + encodeURIComponent(status);
        window.location.href = riwayatUrl + qs;
    }

    function verifyThenRedirect(status) {
        if (finishing) return;
        finishing = true;
        setStatus('Memverifikasi pembayaran...');
        fetch(verifyUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ order_id: orderId })
        }).finally(function() {
            redirectToRiwayat(status);
        });
    }

    function openSnap() {
        if (paying || finishing || !window.snap) return;
        paying = true;
        setStatus('Selesaikan pembayaran di jendela Midtrans.');
        snap.pay(snapToken, {
            onSuccess: function() {
                verifyThenRedirect('success');
            },
            onPending: function() {
                verifyThenRedirect('pending');
            },
            onError: function() {
                verifyThenRedirect('error');
            },
            onClose: function() {
                // User may have paid before closing — always verify once
                verifyThenRedirect('closed');
            }
        });
    }

    document.getElementById('pay-button').addEventListener('click', function() {
        if (finishing) return;
        paying = false;
        openSnap();
    });

    // Auto-open Snap on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', openSnap);
    } else {
        openSnap();
    }
})();
</script>
@endpush
