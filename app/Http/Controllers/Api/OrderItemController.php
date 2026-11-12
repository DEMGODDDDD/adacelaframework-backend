<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemResource;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'sometimes|integer|exists:orders,id',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = OrderItem::query()->with('product.category');

        if (isset($validated['order_id'])) {
            $query->where('order_id', $validated['order_id']);
        }

        if (!$request->user()->isAdmin()) {
            $query->whereHas('order', fn ($orders) => $orders->where('user_id', $request->user()->id));
        }

        return OrderItemResource::collection(
            $query->latest()->paginate($validated['per_page'] ?? 15)
        )->response();
    }

    public function show(Request $request, OrderItem $orderItem): JsonResponse
    {
        abort_unless(
            $request->user()->isAdmin() || $orderItem->order()->where('user_id', $request->user()->id)->exists(),
            403,
            'Unauthorized'
        );

        return (new OrderItemResource($orderItem->load('product.category')))->response();
    }
}
