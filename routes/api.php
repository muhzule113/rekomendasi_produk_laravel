<?php

use Illuminate\Support\Facades\Route;

// Products
Route::get('/produk', [\App\Http\Controllers\Api\ProdukController::class, 'index']);
Route::get('/produk/{id}', [\App\Http\Controllers\Api\ProdukController::class, 'show']);

// Recommendations
Route::get('/rekomendasi', [\App\Http\Controllers\Api\RekomendasiController::class, 'index']);

// Cart routes — moved to web.php for session access
// Review route — defined in web.php (session-based auth) to avoid duplicate route clash

// Protected routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/checkout', [\App\Http\Controllers\Api\CheckoutController::class, 'store']);
});

// Admin-only routes — moved to web.php for session auth


// Midtrans webhook (no auth)
Route::post('/midtrans/notification', [\App\Http\Controllers\Api\MidtransNotificationController::class, 'handle']);
