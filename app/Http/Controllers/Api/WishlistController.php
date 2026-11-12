<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\Product;
use App\Http\Resources\WishlistResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $wishlists = Wishlist::where('user_id', $request->user()->id)
            ->with('product.category')
            ->latest()
            ->paginate($request->input('per_page', 15));

        return WishlistResource::collection($wishlists)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        abort_unless($product->status && $product->category?->status, 422, 'Product is not available');

        $wishlist = Wishlist::firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return (new WishlistResource($wishlist->load('product')))->response()
            ->setStatusCode($wishlist->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, Wishlist $wishlist): JsonResponse
    {
        if ($wishlist->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        return (new WishlistResource($wishlist->load('product')))->response();
    }

    public function destroy(Request $request, Wishlist $wishlist): JsonResponse
    {
        if ($wishlist->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $wishlist->delete();

        return response()->json(['message' => 'Removed from wishlist']);
    }
}