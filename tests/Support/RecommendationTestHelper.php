<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

trait RecommendationTestHelper
{
    protected function seedCatalog(): array
    {
        $catId = DB::table('categories')->insertGetId(['nama_category' => 'Sembako']);

        $products = [];
        foreach (['A', 'B', 'C', 'D', 'E'] as $i => $name) {
            $products[$name] = DB::table('products')->insertGetId([
                'id_category' => $catId,
                'nama_product' => 'Produk ' . $name,
                'deskripsi' => 'Deskripsi ' . $name,
                'harga' => 10000 + ($i * 1000),
                'stok' => 50,
                'status' => 'aktif',
                'terjual' => $i,
                'created_at' => now(),
            ]);
        }

        return $products;
    }

    protected function makeUser(string $email, string $role = 'pelanggan'): int
    {
        return (int) DB::table('users')->insertGetId([
            'nama' => $email,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'aktif',
            'created_at' => now(),
        ]);
    }

    protected function addTransaction(
        int $userId,
        array $productQty,
        string $statusPesanan = 'Selesai',
        string $statusPembayaran = 'Dibayar',
        ?string $tanggal = null,
        ?string $kode = null
    ): int {
        $tanggal = $tanggal ?? now()->toDateTimeString();
        $total = 0;
        foreach ($productQty as $pid => $qty) {
            $total += 10000 * $qty;
        }

        $trxId = DB::table('transactions')->insertGetId([
            'id_user' => $userId,
            'kode_transaksi' => $kode ?? ('TRX-' . uniqid()),
            'tanggal' => $tanggal,
            'subtotal' => $total,
            'total' => $total,
            'alamat_pengiriman' => 'Test',
            'nama_penerima' => 'Tester',
            'no_hp_penerima' => '0800',
            'metode_pembayaran' => 'Tunai',
            'status_pembayaran' => $statusPembayaran,
            'status_pesanan' => $statusPesanan,
            'sumber_data' => 'langsung',
        ]);

        foreach ($productQty as $pid => $qty) {
            DB::table('transaction_items')->insert([
                'id_transaction' => $trxId,
                'id_product' => $pid,
                'nama_snapshot' => 'Produk ' . $pid,
                'harga_snapshot' => 10000,
                'qty' => $qty,
                'harga' => 10000,
                'subtotal' => 10000 * $qty,
            ]);
        }

        return (int) $trxId;
    }
}
