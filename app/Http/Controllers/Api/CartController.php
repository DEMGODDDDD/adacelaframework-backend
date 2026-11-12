<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request->user());

        return (new CartResource($cart->load('items.product.category')))->response();
    }

    public function clear(Request $request): JsonResponse
    {
        $request->user()->cart?->items()->delete();

        return response()->json(['message' => 'Cart cleared']);
    }

    private function getOrCreateCart(User $user): Cart
    {
        return $user->cart()->firstOrCreate([]);
    }
}
