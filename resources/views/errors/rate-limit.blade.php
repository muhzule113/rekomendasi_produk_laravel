@extends('layouts.customer')

@section('title', 'Terlalu Banyak Permintaan — Toko Sinar Manis')

@section('content')
<section style="padding:4rem 1rem;min-height:60vh;background:#f8fafc;">
    <div class="container" style="max-width:620px;">
        <div class="card card-body" style="text-align:center;">
            <div style="width:4rem;height:4rem;margin:0 auto 1.25rem;background:#fef3c7;color:#b45309;border-radius:9999px;display:grid;place-items:center;font-size:1.5rem;">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <h1 style="font-size:1.75rem;font-weight:800;color:var(--primary);">Terlalu Banyak Permintaan</h1>
            <p style="margin:1rem auto;max-width:32rem;color:var(--muted-foreground);line-height:1.7;">{{ $message }}</p>
            @if(($retryAfter ?? 0) > 0)
                <p style="font-size:.875rem;color:var(--muted-foreground);">Waktu tunggu: {{ $retryAfter }} detik.</p>
            @endif
            <a href="{{ route($backRoute ?? 'home') }}" class="btn btn-primary btn-md" style="margin-top:1.5rem;">Kembali</a>
        </div>
    </div>
</section>
@endsection
