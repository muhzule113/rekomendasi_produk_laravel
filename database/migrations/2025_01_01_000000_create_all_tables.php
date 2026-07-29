<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. users
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id('id_user');
                $table->string('nama', 100);
                $table->string('email', 100)->unique();
                $table->string('no_hp', 20)->nullable();
                $table->text('alamat')->nullable();
                $table->string('password', 255);
                $table->enum('role', ['pelanggan', 'admin'])->default('pelanggan');
                $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
                $table->dateTime('created_at')->useCurrent();
            });
        }

        // 2. categories
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id('id_category');
                $table->string('nama_category', 100);
            });
        }

        // 3. products
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id('id_product');
                $table->foreignId('id_category')->constrained('categories', 'id_category');
                $table->string('nama_product', 150);
                $table->text('deskripsi')->nullable();
                $table->decimal('harga', 12, 2);
                $table->integer('stok')->default(0);
                $table->string('foto', 255)->nullable();
                $table->decimal('rating', 3, 2)->default(0);
                $table->integer('terjual')->default(0);
                $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
                $table->dateTime('created_at')->useCurrent();
            });
        }

        // 4. transactions
        if (! Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id('id_transaction');
                $table->foreignId('id_user')->constrained('users', 'id_user');
                $table->unsignedInteger('id_upload')->nullable()->comment('NULL = transaksi langsung, FILLED = dari pipeline upload');
                $table->string('kode_transaksi', 30)->nullable();
                $table->dateTime('tanggal')->useCurrent();
                $table->decimal('subtotal', 12, 2)->nullable();
                $table->decimal('ongkir', 12, 2)->default(0);
                $table->decimal('diskon', 12, 2)->default(0);
                $table->decimal('total', 12, 2);
                $table->text('alamat_pengiriman')->nullable();
                $table->string('nama_penerima', 100)->nullable();
                $table->string('no_hp_penerima', 20)->nullable();
                $table->enum('metode_pembayaran', ['Tunai', 'Transfer', 'QRIS', 'Midtrans'])->default('Tunai');
                $table->enum('status_pembayaran', ['Belum Dibayar', 'Pending', 'Dibayar', 'Gagal', 'Expired', 'Dibatalkan', 'Refund'])->default('Belum Dibayar');
                $table->enum('status_pesanan', ['Menunggu Pembayaran', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan'])->default('Diproses');
                $table->enum('sumber_data', ['langsung', 'upload_csv', 'upload_excel'])->default('langsung');
                $table->string('midtrans_order_id', 100)->nullable();
                $table->string('snap_token', 255)->nullable();
                $table->string('payment_type', 50)->nullable();
                $table->string('fraud_status', 50)->nullable();
                $table->string('payment_status', 50)->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->dateTime('expired_at')->nullable();
                $table->json('payment_payload')->nullable();
                $table->dateTime('updated_at')->nullable();

                $table->index('id_upload');
                $table->index('midtrans_order_id');
                $table->index('tanggal');
                $table->index('sumber_data');
            });
        }

        // 5. transaction_items
        if (! Schema::hasTable('transaction_items')) {
            Schema::create('transaction_items', function (Blueprint $table) {
                $table->id('id_item');
                $table->foreignId('id_transaction')->constrained('transactions', 'id_transaction')->cascadeOnDelete();
                $table->foreignId('id_product')->constrained('products', 'id_product');
                $table->string('nama_snapshot', 150)->nullable()->comment('Salinan nama produk saat transaksi');
                $table->decimal('harga_snapshot', 12, 2)->nullable()->comment('Salinan harga produk saat transaksi');
                $table->integer('qty');
                $table->decimal('harga', 12, 2);
                $table->decimal('subtotal', 12, 2);
            });
        }

        // 6. product_similarity
        if (! Schema::hasTable('product_similarity')) {
            Schema::create('product_similarity', function (Blueprint $table) {
                $table->id('id_similarity');
                $table->foreignId('product_a')->constrained('products', 'id_product')->cascadeOnDelete();
                $table->foreignId('product_b')->constrained('products', 'id_product')->cascadeOnDelete();
                $table->decimal('score', 8, 6);
                $table->integer('co_occurrence')->default(0);
                $table->string('source', 20)->default('cf_purchase');
                $table->dateTime('updated_at')->useCurrent();

                $table->unique(['product_a', 'product_b', 'source'], 'uk_pair_source');
                $table->index('product_a');
                $table->index('product_b');
                $table->index('score');
            });
        }

        // 7. recommendation_logs
        if (! Schema::hasTable('recommendation_logs')) {
            Schema::create('recommendation_logs', function (Blueprint $table) {
                $table->id('id_log');
                $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
                $table->foreignId('id_product')->constrained('products', 'id_product')->cascadeOnDelete();
                $table->string('alasan', 255)->nullable();
                $table->decimal('score', 8, 6)->nullable();
                $table->dateTime('created_at')->useCurrent();

                $table->index('id_user');
                $table->index('id_product');
                $table->index('created_at');
            });
        }

        // 8. data_uploads
        if (! Schema::hasTable('data_uploads')) {
            Schema::create('data_uploads', function (Blueprint $table) {
                $table->unsignedInteger('id_upload')->autoIncrement();
                $table->foreignId('id_user')->constrained('users', 'id_user');
                $table->enum('sumber', ['transaksi', 'produk'])->default('transaksi');
                $table->string('nama_file_asli', 255);
                $table->string('nama_file_disk', 255);
                $table->enum('tipe_file', ['csv', 'xlsx', 'xls']);
                $table->unsignedInteger('ukuran_kb');
                $table->string('path_file', 500);
                $table->string('file_hash', 64)->nullable()->comment('SHA256 untuk cek duplikat upload');
                $table->unsignedInteger('total_baris')->nullable();
                $table->unsignedInteger('baris_valid')->nullable();
                $table->unsignedInteger('baris_invalid')->nullable();
                $table->unsignedInteger('baris_duplikat')->nullable();
                $table->unsignedInteger('baris_diimport')->nullable();
                $table->enum('status', ['menunggu', 'memproses', 'selesai', 'gagal'])->default('menunggu');
                $table->text('pesan_error')->nullable();
                $table->json('kolom_mapping')->nullable();
                $table->dateTime('uploaded_at')->useCurrent();
                $table->dateTime('processed_at')->nullable();

                $table->index('id_user');
                $table->index('status');
                $table->index('uploaded_at');
            });
        }

        // 9. upload_logs
        if (! Schema::hasTable('upload_logs')) {
            Schema::create('upload_logs', function (Blueprint $table) {
                $table->unsignedInteger('id_log')->autoIncrement();
                $table->unsignedInteger('id_upload');
                $table->unsignedInteger('nomor_baris');
                $table->enum('status_baris', ['imported', 'invalid', 'duplikat', 'skip']);
                $table->text('data_mentah')->nullable();
                $table->text('data_bersih')->nullable();
                $table->unsignedInteger('id_transaction')->nullable();
                $table->string('keterangan', 500)->nullable();
                $table->dateTime('created_at')->useCurrent();

                $table->foreign('id_upload')->references('id_upload')->on('data_uploads')->cascadeOnDelete();
                $table->index('id_upload');
                $table->index('status_baris');
                $table->index('id_transaction');
            });
        }

        // 10. cf_run_logs
        if (! Schema::hasTable('cf_run_logs')) {
            Schema::create('cf_run_logs', function (Blueprint $table) {
                $table->unsignedInteger('id_cf_run')->autoIncrement();
                $table->dateTime('started_at');
                $table->dateTime('finished_at')->nullable();
                $table->unsignedInteger('total_users')->nullable();
                $table->unsignedInteger('total_products')->nullable();
                $table->unsignedInteger('total_pairs')->nullable();
                $table->decimal('coverage', 5, 2)->nullable();
                $table->decimal('max_score', 8, 6)->nullable();
                $table->decimal('avg_score', 8, 6)->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->enum('status', ['running', 'success', 'failed'])->default('running');
                $table->text('error_message')->nullable();
                $table->dateTime('created_at')->useCurrent();
            });
        }

        // 11. product_reviews
        if (! Schema::hasTable('product_reviews')) {
            Schema::create('product_reviews', function (Blueprint $table) {
                $table->unsignedInteger('id_review')->autoIncrement();
                $table->foreignId('id_product')->constrained('products', 'id_product')->cascadeOnDelete();
                $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->text('komentar')->nullable();
                $table->dateTime('created_at')->useCurrent();

                $table->unique(['id_user', 'id_product'], 'uk_user_product');
            });
        }

        // 12. product_views
        if (! Schema::hasTable('product_views')) {
            Schema::create('product_views', function (Blueprint $table) {
                $table->unsignedInteger('id_view')->autoIncrement();
                $table->foreignId('id_product')->constrained('products', 'id_product')->cascadeOnDelete();
                $table->foreignId('id_user')->nullable()->constrained('users', 'id_user')->nullOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->dateTime('viewed_at')->useCurrent();

                $table->index('id_product');
                $table->index('id_user');
            });
        }

        // 13. cart
        if (! Schema::hasTable('cart')) {
            Schema::create('cart', function (Blueprint $table) {
                $table->unsignedInteger('id_cart')->autoIncrement();
                $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
                $table->foreignId('id_product')->constrained('products', 'id_product')->cascadeOnDelete();
                $table->unsignedInteger('qty')->default(1);
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('updated_at')->useCurrent();

                $table->unique(['id_user', 'id_product'], 'uk_user_product');
            });
        }

        // 14. system_settings
        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->unsignedInteger('id_setting')->autoIncrement();
                $table->string('setting_key', 100)->unique();
                $table->text('setting_value')->nullable();
                $table->dateTime('updated_at')->useCurrent();
            });
        }

        // 15. transaction_status_logs
        if (! Schema::hasTable('transaction_status_logs')) {
            Schema::create('transaction_status_logs', function (Blueprint $table) {
                $table->unsignedInteger('id_log')->autoIncrement();
                $table->foreignId('id_transaction')->constrained('transactions', 'id_transaction')->cascadeOnDelete();
                $table->string('field_changed', 50);
                $table->string('old_value', 50);
                $table->string('new_value', 50);
                $table->foreignId('diubah_oleh')->nullable()->constrained('users', 'id_user')->nullOnDelete();
                $table->dateTime('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_status_logs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('cart');
        Schema::dropIfExists('product_views');
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('cf_run_logs');
        Schema::dropIfExists('upload_logs');
        Schema::dropIfExists('data_uploads');
        Schema::dropIfExists('recommendation_logs');
        Schema::dropIfExists('product_similarity');
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('users');
    }
};
