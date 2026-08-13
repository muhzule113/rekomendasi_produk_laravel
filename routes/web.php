<?php

use Illuminate\Support\Facades\Route;

// ── Auth ──
Route::get('/login', fn() => view('auth.login'))->name('login');
Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login'])->name('login.post');
Route::get('/register', fn() => view('auth.register'))->name('register');
Route::post('/register', [\App\Http\Controllers\AuthController::class, 'register'])
    ->middleware('throttle:registration')
    ->name('register.post');
Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout'])->name('logout');

// ── Email Verification ──
Route::get('/email/verify', [\App\Http\Controllers\EmailVerificationController::class, 'notice'])
    ->middleware(['auth', \App\Http\Middleware\PelangganMiddleware::class])
    ->name('verification.notice');
Route::get('/email/verification-status', [\App\Http\Controllers\EmailVerificationController::class, 'check'])
    ->middleware(['auth', \App\Http\Middleware\PelangganMiddleware::class])
    ->name('verification.check');
Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\EmailVerificationController::class, 'verify'])
    ->middleware(['auth', \App\Http\Middleware\PelangganMiddleware::class, 'signed'])
    ->name('verification.verify');
Route::post('/email/verification-notification', [\App\Http\Controllers\EmailVerificationController::class, 'send'])
    ->middleware(['auth', \App\Http\Middleware\PelangganMiddleware::class, 'throttle:verification-resend'])
    ->name('verification.send');

// ── Customer Pages ──
Route::get('/', [\App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/produk', [\App\Http\Controllers\ProdukController::class, 'index'])->name('produk');
Route::get('/produk/{id}', [\App\Http\Controllers\ProdukController::class, 'detail'])->name('produk.detail');
Route::get('/keranjang', [\App\Http\Controllers\CartController::class, 'index'])->name('keranjang');
Route::get('/rekomendasi', [\App\Http\Controllers\RekomendasiController::class, 'index'])->name('rekomendasi');

// Cart API (needs session, so in web routes)
Route::get('/api/cart', [\App\Http\Controllers\Api\CartController::class, 'index'])->name('api.cart.index');
Route::post('/api/cart', [\App\Http\Controllers\Api\CartController::class, 'store'])->name('api.cart.store');
Route::put('/api/cart', [\App\Http\Controllers\Api\CartController::class, 'update'])->name('api.cart.update');
Route::delete('/api/cart', [\App\Http\Controllers\Api\CartController::class, 'destroy'])->name('api.cart.destroy');
// Midtrans payment verification (customer-facing)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/verify-payment', [\App\Http\Controllers\Api\VerifyPaymentController::class, 'verify'])->name('verify.payment');
});

Route::middleware(['auth', \App\Http\Middleware\PelangganMiddleware::class, 'verified'])->group(function () {
    Route::get('/checkout', [\App\Http\Controllers\CheckoutController::class, 'index'])->name('checkout');
    Route::post('/checkout', [\App\Http\Controllers\CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/riwayat', [\App\Http\Controllers\RiwayatController::class, 'index'])->name('riwayat');
    Route::post('/api/review', [\App\Http\Controllers\Api\ReviewController::class, 'store'])->name('api.review.store');
});

// ── Admin Panel ──
Route::prefix('admin')->name('admin.')->middleware(['auth', \App\Http\Middleware\AdminMiddleware::class])->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/produk', [\App\Http\Controllers\Admin\ProdukController::class, 'index'])->name('produk');
    Route::post('/produk', [\App\Http\Controllers\Admin\ProdukController::class, 'store'])->name('produk.store');
    Route::put('/produk/{id}', [\App\Http\Controllers\Admin\ProdukController::class, 'update'])->name('produk.update');
    Route::delete('/produk/{id}', [\App\Http\Controllers\Admin\ProdukController::class, 'destroy'])->name('produk.destroy');
    Route::delete('/produk', [\App\Http\Controllers\Admin\ProdukController::class, 'bulkDestroy'])->name('produk.bulk-destroy');
    Route::get('/pelanggan', [\App\Http\Controllers\Admin\PelangganController::class, 'index'])->name('pelanggan');
    Route::delete('/pelanggan', [\App\Http\Controllers\Admin\PelangganController::class, 'bulkDestroy'])->name('pelanggan.bulk-destroy');
    Route::get('/pelanggan/{id}/transaksi', [\App\Http\Controllers\Admin\PelangganController::class, 'transaksi'])->name('pelanggan.transaksi');
    Route::get('/transaksi', [\App\Http\Controllers\Admin\TransaksiController::class, 'index'])->name('transaksi');
    Route::delete('/transaksi', [\App\Http\Controllers\Admin\TransaksiController::class, 'bulkDestroy'])->name('transaksi.bulk-destroy');
    Route::post('/transaksi/{id}/status', [\App\Http\Controllers\Admin\TransaksiController::class, 'updateStatus'])->name('transaksi.status');
    Route::get('/transaksi/{id}', [\App\Http\Controllers\Admin\TransaksiController::class, 'detail'])->name('transaksi.detail');
    Route::get('/analisis', [\App\Http\Controllers\Admin\AnalisisController::class, 'index'])->name('analisis');
    Route::get('/reviews', [\App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('reviews');
    // Upload is initiated from the page that owns the data type. Keep the old
    // GET route as a compatibility redirect for existing bookmarks.
    Route::get('/upload', [\App\Http\Controllers\Admin\UploadController::class, 'index'])->name('upload');
    Route::post('/upload', [\App\Http\Controllers\Admin\UploadController::class, 'store'])->name('upload.store');
    Route::post('/transaksi/upload', [\App\Http\Controllers\Admin\UploadController::class, 'storeTransaksi'])->name('transaksi.upload');
    Route::post('/produk/upload', [\App\Http\Controllers\Admin\UploadController::class, 'storeProduk'])->name('produk.upload');
    Route::get('/upload-history', [\App\Http\Controllers\Admin\UploadHistoryController::class, 'legacy'])->name('upload-history');
    Route::get('/upload-history/transaksi/{id?}', [\App\Http\Controllers\Admin\UploadHistoryController::class, 'transaksi'])
        ->whereNumber('id')
        ->name('upload-history.transaksi');
    Route::get('/upload-history/produk/{id?}', [\App\Http\Controllers\Admin\UploadHistoryController::class, 'produk'])
        ->whereNumber('id')
        ->name('upload-history.produk');
    Route::delete('/upload-history/{id}', [\App\Http\Controllers\Admin\UploadHistoryController::class, 'destroy'])->name('upload-history.destroy');
    Route::post('/similarity', [\App\Http\Controllers\Api\SimilarityController::class, 'recalculate'])->name('similarity.recalculate');
    Route::post('/upload-data', [\App\Http\Controllers\Api\UploadController::class, 'store'])->name('upload-data.store');
    Route::get('/pipeline-status', [\App\Http\Controllers\Api\PipelineStatusController::class, 'show'])->name('pipeline-status');
    Route::get('/pipeline-data/dashboard', [\App\Http\Controllers\Api\DashboardController::class, 'index'])->name('pipeline.dashboard');
    Route::get('/pipeline-data/laporan', [\App\Http\Controllers\Api\LaporanController::class, 'index'])->name('pipeline.laporan');
    Route::post('/pipeline-data/verify-payment/{id}', [\App\Http\Controllers\Api\VerifyPaymentController::class, 'verify'])->name('pipeline.verify-payment');
    Route::get('/laporan', [\App\Http\Controllers\Admin\LaporanController::class, 'index'])->name('laporan');
});
