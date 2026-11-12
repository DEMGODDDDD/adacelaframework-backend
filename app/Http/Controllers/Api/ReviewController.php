<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Order;
use App\Models\Product;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Review::query()->with(['user', 'product']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('user_id') && $request->user()->isAdmin()) {
            $query->where('user_id', $request->input('user_id'));
        } elseif (!$request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        if (!$request->user()->isAdmin()) {
            $query->where('moderation_status', 'approved');
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->input('rating'));
        }

        $reviews = $query->latest()->paginate($request->input('per_page', 15));

        return ReviewResource::collection($reviews)->response();
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|in:pending,approved,flagged',
            'rating' => 'sometimes|integer|min:1|max:5',
            'search' => 'sometimes|string|max:255',
        ]);

        $reviews = Review::query()
            ->with(['user', 'product'])
            ->when(isset($filters['status']), fn ($query) => $query->where('moderation_status', $filters['status']))
            ->when(isset($filters['rating']), fn ($query) => $query->where('rating', $filters['rating']))
            ->when(isset($filters['search']), fn ($query) => $query->where(function ($query) use ($filters): void {
                $query->whereHas('user', fn ($user) => $user
                    ->where('first_name', 'like', "%{$filters['search']}%")
                    ->orWhere('last_name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%"))
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$filters['search']}%"));
            }))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        return ReviewResource::collection($reviews)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'delivered')
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found or not delivered'], 422);
        }

        if (!$order->items()->where('product_id', $validated['product_id'])->exists()) {
            return response()->json(['message' => 'Product was not included in this order'], 422);
        }

        $hasReview = Review::where('user_id', $request->user()->id)
            ->where('product_id', $validated['product_id'])
            ->where('order_id', $validated['order_id'])
            ->exists();

        if ($hasReview) {
            return response()->json(['message' => 'Already reviewed this product for this order'], 422);
        }

        $review = Review::create([
            'user_id' => $request->user()->id,
            'product_id' => $validated['product_id'],
            'order_id' => $validated['order_id'],
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        return (new ReviewResource($review->load('user', 'product')))->response()->setStatusCode(201);
    }

    public function show(Request $request, Review $review): JsonResponse
    {
        abort_unless(
            $review->user_id === $request->user()->id || $request->user()->isAdmin(),
            403,
            'Unauthorized'
        );

        return (new ReviewResource($review->load('user', 'product')))->response();
    }

    public function update(Request $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->user()->isAdmin()
            ? $request->validate([
                'rating' => 'sometimes|required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
                'moderation_status' => 'sometimes|required|in:pending,approved,flagged',
                'admin_reply' => 'nullable|string|max:2000',
            ])
            : $request->validate([
                'rating' => 'sometimes|required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
            ]);

        $review->update($validated);

        return (new ReviewResource($review->load('user', 'product')))->response();
    }

    public function destroy(Review $review): JsonResponse
    {
        if ($review->user_id !== request()->user()->id && !request()->user()->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        $review->delete();

        return response()->json(['message' => 'Review deleted successfully']);
    }
}