<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    private function rejectIfAdmin(): ?JsonResponse
    {
        $user = auth()->user();
        if ($user && $user->role === 'admin') {
            return response()->json(['status' => false, 'message' => 'Admin tidak dapat menggunakan keranjang']);
        }
        return null;
    }

    public function index(): JsonResponse
    {
        if ($reject = $this->rejectIfAdmin()) return $reject;

        return response()->json(['status' => true, 'data' => $this->cartService->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($reject = $this->rejectIfAdmin()) return $reject;

        $cart = $this->cartService->get();
        $productId = (int) $request->input('product_id', 0);
        $qty = (int) $request->input('qty', 1);

        if (!$productId) {
            return response()->json(['status' => false, 'message' => 'Data tidak valid']);
        }

        $product = DB::table('products')->where('id_product', $productId)->first();
        if (!$product) {
            return response()->json(['status' => false, 'message' => 'Produk tidak ditemukan']);
        }

        $currentCartQty = $cart[$productId] ?? 0;
        $requestedQty = $currentCartQty + $qty;

        if ($product->stok < $requestedQty) {
            return response()->json(['status' => false, 'message' => 'Stok tidak mencukupi']);
        }

        $cart[$productId] = $requestedQty;
        $this->cartService->put($cart);

        return response()->json([
            'status' => true,
            'message' => 'Produk berhasil ditambahkan ke keranjang',
            'cart_count' => array_sum($cart),
            'remaining_stok' => max(0, (int) $product->stok - $requestedQty),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        if ($reject = $this->rejectIfAdmin()) return $reject;

        $cart = $this->cartService->get();
        $productId = (int) $request->input('product_id', 0);
        $qty = (int) $request->input('qty', 1);

        if (!$productId || !isset($cart[$productId])) {
            return response()->json(['status' => false, 'message' => 'Produk tidak ditemukan']);
        }

        if ($qty > 0) {
            $product = DB::table('products')->where('id_product', $productId)->first();
            if ($product && $product->stok < $qty) {
                return response()->json(['status' => false, 'message' => 'Stok tidak mencukupi']);
            }
            $cart[$productId] = $qty;
        } else {
            unset($cart[$productId]);
        }

        $this->cartService->put($cart);

        return response()->json([
            'status' => true,
            'message' => 'Keranjang diperbarui',
            'cart_count' => array_sum($cart),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        if ($reject = $this->rejectIfAdmin()) return $reject;

        $cart = $this->cartService->get();
        $productId = (int) $request->input('product_id', 0);

        if (!$productId || !isset($cart[$productId])) {
            return response()->json(['status' => false, 'message' => 'Produk tidak ditemukan']);
        }

        unset($cart[$productId]);
        $this->cartService->put($cart);

        return response()->json([
            'status' => true,
            'message' => 'Produk dihapus dari keranjang',
            'cart_count' => array_sum($cart),
        ]);
    }
}
