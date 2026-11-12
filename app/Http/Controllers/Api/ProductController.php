<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category_id' => 'sometimes|integer|exists:categories,id',
            'search' => 'sometimes|string|max:255',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => 'sometimes|numeric|min:0',
            'min_rating' => 'sometimes|numeric|min:0|max:5',
            'sort' => 'sometimes|in:latest,oldest,price_low,price_high,rating,name_asc,name_desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'wishlist_only' => 'sometimes|boolean',
        ]);
        $query = Product::query()
            ->when(!$request->user('sanctum')?->isAdmin(), fn ($query) => $query->where('products.status', true))
            ->when(!$request->user('sanctum')?->isAdmin(), fn ($query) => $query
                ->whereHas('category', fn ($category) => $category->where('status', true)))
            ->with(['category', 'primaryImage'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        if ($request->user('sanctum')?->isAdmin() && $request->filled('status')) {
            $query->where('products.status', $request->boolean('status'));
        }

        if ($request->boolean('featured')) {
            $query->where('featured', true);
        }

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('sku', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['min_price'])) {
            $query->whereRaw(
                'CASE WHEN sale_price IS NOT NULL AND sale_price < price THEN sale_price ELSE price END >= CAST(? AS DECIMAL)',
                [$filters['min_price']]
            );
        }

        if (isset($filters['max_price'])) {
            $query->whereRaw(
                'CASE WHEN sale_price IS NOT NULL AND sale_price < price THEN sale_price ELSE price END <= CAST(? AS DECIMAL)',
                [$filters['max_price']]
            );
        }

        if (isset($filters['min_rating']) && $filters['min_rating'] > 0) {
            $query->whereRaw(
                "COALESCE((SELECT AVG(rating) FROM reviews WHERE reviews.product_id = products.id AND reviews.moderation_status = 'approved'), 0) >= ?",
                [$filters['min_rating']]
            );
        }

        if ($filters['wishlist_only'] ?? false) {
            $user = $request->user('sanctum');
            if ($user) {
                $query->whereHas('wishlists', fn ($wishlists) => $wishlists->where('user_id', $user->id));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $sort = $filters['sort'] ?? 'latest';
        match ($sort) {
            'price_low' => $query->orderByRaw('CASE WHEN sale_price IS NOT NULL AND sale_price < price THEN sale_price ELSE price END asc'),
            'price_high' => $query->orderByRaw('CASE WHEN sale_price IS NOT NULL AND sale_price < price THEN sale_price ELSE price END desc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            'oldest' => $query->oldest(),
            'rating' => $query->orderByDesc('reviews_avg_rating'),
            default => $query->latest(),
        };

        $products = $query->paginate($filters['per_page'] ?? 15);

        return ProductResource::collection($products)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'brand' => 'nullable|string|max:255',
            'status' => 'boolean',
            'featured' => 'boolean',
            'image_url' => 'nullable|url|max:2048',
            'image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:5120',
        ]);

        $imageUrl = $validated['image_url'] ?? null;
        unset($validated['image_url'], $validated['image']);
        $product = Product::create($validated);
        $this->saveProductImage($request, $product, $imageUrl);

        return (new ProductResource($product->load(['category', 'primaryImage'])))->response()->setStatusCode(201);
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->status && $product->category?->status, 404);

        return (new ProductResource(
            $product->load(['category', 'images', 'reviews.user', 'inventory'])->loadCount('reviews')
                ->loadAvg('reviews', 'rating')
        ))->response();
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'sometimes|required|string|max:100|unique:products,sku,' . $product->id,
            'category_id' => 'sometimes|required|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'sometimes|required|integer|min:0',
            'brand' => 'nullable|string|max:255',
            'status' => 'boolean',
            'featured' => 'boolean',
            'image_url' => 'nullable|url|max:2048',
            'image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:5120',
        ]);

        $imageUrl = $validated['image_url'] ?? null;
        unset($validated['image_url'], $validated['image']);
        $product->update($validated);
        if ($request->hasFile('image') || $imageUrl !== null) {
            $this->saveProductImage($request, $product, $imageUrl);
        }

        return (new ProductResource($product->load(['category', 'primaryImage'])))->response();
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAdmin($request);

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Admin only');
    }

    private function saveProductImage(Request $request, Product $product, ?string $imageUrl): void
    {
        if ($request->hasFile('image')) {
            $image = $request->file('image')->store("products/{$product->id}", 'public');
            $imageUrl = '/storage/'.$image;
        }

        if ($imageUrl !== null) {
            $product->images()->updateOrCreate(
                ['is_primary' => true],
                ['image' => $imageUrl, 'sort_order' => 0]
            );
        }
    }
}