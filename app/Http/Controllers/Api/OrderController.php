<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Product;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => 'sometimes|in:pending,processing,shipped,delivered,cancelled,return_refund',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Order::query()->with(['items.product', 'user']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!$request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        $orders = $query->latest()->paginate($filters['per_page'] ?? 15);

        return OrderResource::collection($orders)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_name' => 'required|string|max:255',
            'shipping_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string',
            'shipping_city' => 'nullable|string|max:100',
            'shipping_state' => 'nullable|string|max:100',
            'shipping_zip' => 'nullable|string|max:20',
            'shipping_country' => 'nullable|string|max:100',
            'payment_method' => 'required|in:cod,gcash,paypal',
            'notes' => 'nullable|string',
        ]);

        $cart = $request->user()->cart;
        if (!$cart) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        return DB::transaction(function () use ($request, $cart, $validated) {
            $lockedCart = Cart::query()->whereKey($cart->id)->lockForUpdate()->first();
            $items = $lockedCart?->items()->with('product.category')->get() ?? collect();

            if ($items->isEmpty()) {
                return response()->json(['message' => 'Cart is empty'], 422);
            }

            $products = collect();
            foreach ($items as $item) {
                $product = Product::query()->with('category')->lockForUpdate()->findOrFail($item->product_id);
                if (!$product->status || !$product->category?->status) {
                    abort(422, "Product {$product->name} is no longer available");
                }
                if ($product->stock_quantity < $item->quantity) {
                    abort(422, "Insufficient stock for {$product->name}");
                }
                $products->put($product->id, $product);
            }

            $subtotal = $items->sum(fn ($item) => $item->quantity * $products->get($item->product_id)->final_price);
            $shippingFee = $subtotal >= 1000 ? 0 : 150; // Free shipping over ₱1000
            $tax = round($subtotal * 0.12, 2); // 12% VAT
            $total = round($subtotal + $shippingFee + $tax, 2);

            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(Str::uuid()->toString()),
                'user_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'tax' => $tax,
                'total' => $total,
                'status' => 'pending',
                'payment_method' => $validated['payment_method'],
                'shipping_name' => $validated['shipping_name'],
                'shipping_phone' => $validated['shipping_phone'],
                'shipping_address' => $validated['shipping_address'],
                'shipping_city' => $validated['shipping_city'] ?? null,
                'shipping_state' => $validated['shipping_state'] ?? null,
                'shipping_zip' => $validated['shipping_zip'] ?? null,
                'shipping_country' => $validated['shipping_country'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item->quantity,
                    'price' => $product->final_price,
                    'total' => $product->final_price * $item->quantity,
                ]);

                $product->decrement('stock_quantity', $item->quantity);
                $product->inventory()->decrement('stock_quantity', $item->quantity, ['updated_at' => now()]);
            }

            $lockedCart->items()->delete();

            return (new OrderResource($order->load('items.product')))->response()->setStatusCode(201);
        });
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if (!$request->user()->isAdmin() && $order->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        return (new OrderResource($order->load('items.product')))->response();
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Admin only');
        }

        $validated = $request->validate([
            'status' => 'sometimes|required|in:pending,processing,shipped,delivered,cancelled,return_refund',
            'notes' => 'nullable|string',
        ]);

        if (isset($validated['status'])) {
            if ($validated['status'] === 'shipped' && !$order->shipped_at) {
                $validated['shipped_at'] = now();
            } elseif ($validated['status'] === 'delivered' && !$order->delivered_at) {
                $validated['delivered_at'] = now();
            }
        }

        $order->update($validated);

        return (new OrderResource($order->load(['items.product', 'user'])))->response();
    }

    public function destroy(Order $order): JsonResponse
    {
        abort_unless(request()->user()?->isAdmin(), 403, 'Admin only');

        $order->delete();

        return response()->json(['message' => 'Order deleted successfully']);
    }
}