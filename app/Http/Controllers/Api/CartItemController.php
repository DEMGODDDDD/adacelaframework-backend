<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Http\Resources\CartItemResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CartItemController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $request->user()->cart()->firstOrCreate([]);
        $cartItem = DB::transaction(function () use ($cart, $validated): CartItem {
            $product = Product::query()->lockForUpdate()->findOrFail($validated['product_id']);

            if (!$product->status || !$product->category?->status) {
                abort(422, 'Product is not available');
            }

            $cartItem = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();
            $quantity = ($cartItem?->quantity ?? 0) + $validated['quantity'];

            if ($product->stock_quantity < $quantity) {
                abort(422, 'Insufficient stock');
            }

            if ($cartItem) {
                $cartItem->update(['quantity' => $quantity]);
                return $cartItem;
            }

            return CartItem::query()->create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]);
        });

        return (new CartItemResource($cartItem->load('product')))
            ->response()
            ->setStatusCode($cartItem->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->authorizeCartItem($request, $cartItem);

        return (new CartItemResource($cartItem->load('product.category')))->response();
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->authorizeCartItem($request, $cartItem);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($cartItem, $validated): void {
            $lockedCartItem = CartItem::query()
                ->whereKey($cartItem->id)
                ->lockForUpdate()
                ->firstOrFail();
            $product = Product::query()
                ->with('category')
                ->lockForUpdate()
                ->findOrFail($lockedCartItem->product_id);

            if (!$product->status || !$product->category?->status) {
                abort(422, 'Product is not available');
            }

            if ($product->stock_quantity < $validated['quantity']) {
                abort(422, 'Insufficient stock');
            }

            $lockedCartItem->update($validated);
        });

        return (new CartItemResource($cartItem->refresh()->load('product')))->response();
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->authorizeCartItem($request, $cartItem);

        $cartItem->delete();

        return response()->json(['message' => 'Item removed from cart']);
    }

    private function authorizeCartItem(Request $request, CartItem $cartItem): void
    {
        if ($cartItem->cart->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }
    }
}