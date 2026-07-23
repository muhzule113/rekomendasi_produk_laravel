<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CartService
{
    /** @return array<int,int> product_id => qty */
    public function get(): array
    {
        return $this->normalize(session('cart', []));
    }

    public function put(array $cart): void
    {
        $cart = $this->normalize($cart);
        session(['cart' => $cart]);
        $this->persist($cart);
    }

    public function clear(): void
    {
        session()->forget('cart');
        $userId = $this->pelangganId();
        if ($userId) {
            DB::table('cart')->where('id_user', $userId)->delete();
        }
    }

    /** Restore DB cart into session on login. Merge guest session if any. */
    public function restoreOnLogin(int $userId): void
    {
        $sessionCart = $this->normalize(session('cart', []));
        $dbCart = $this->loadFromDb($userId);

        $merged = $dbCart;
        foreach ($sessionCart as $pid => $qty) {
            $merged[$pid] = max($merged[$pid] ?? 0, $qty);
        }

        $merged = $this->capByStock($merged);
        session(['cart' => $merged]);
        $this->saveToDb($userId, $merged);
    }

    private function persist(array $cart): void
    {
        $userId = $this->pelangganId();
        if (!$userId) {
            return;
        }
        $this->saveToDb($userId, $cart);
    }

    private function saveToDb(int $userId, array $cart): void
    {
        DB::table('cart')->where('id_user', $userId)->delete();
        if (empty($cart)) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($cart as $pid => $qty) {
            $rows[] = [
                'id_user' => $userId,
                'id_product' => $pid,
                'qty' => $qty,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('cart')->insert($rows);
    }

    private function loadFromDb(int $userId): array
    {
        $rows = DB::table('cart')
            ->where('id_user', $userId)
            ->pluck('qty', 'id_product');

        $cart = [];
        foreach ($rows as $pid => $qty) {
            $cart[(int) $pid] = (int) $qty;
        }
        return $cart;
    }

    private function pelangganId(): ?int
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'pelanggan') {
            return null;
        }
        return (int) $user->id_user;
    }

    private function normalize(array $cart): array
    {
        $out = [];
        foreach ($cart as $pid => $qty) {
            $pid = (int) $pid;
            $qty = is_array($qty) ? (int) ($qty['qty'] ?? 0) : (int) $qty;
            if ($pid > 0 && $qty > 0) {
                $out[$pid] = $qty;
            }
        }
        return $out;
    }

    private function capByStock(array $cart): array
    {
        if (empty($cart)) {
            return [];
        }
        $stocks = DB::table('products')
            ->whereIn('id_product', array_keys($cart))
            ->where('status', 'aktif')
            ->pluck('stok', 'id_product');

        $out = [];
        foreach ($cart as $pid => $qty) {
            if (!isset($stocks[$pid])) {
                continue;
            }
            $cap = min($qty, max(0, (int) $stocks[$pid]));
            if ($cap > 0) {
                $out[$pid] = $cap;
            }
        }
        return $out;
    }
}
