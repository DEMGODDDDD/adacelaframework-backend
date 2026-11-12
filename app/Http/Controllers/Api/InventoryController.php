<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'low_stock' => 'sometimes|boolean',
        ]);

        $inventories = Inventory::query()
            ->with('product.category')
            ->orderBy('product_id')
            ->when(
                $validated['low_stock'] ?? false,
                fn ($query) => $query->whereColumn('stock_quantity', '<=', 'reorder_level')
            )
            ->paginate($validated['per_page'] ?? 15);

        return InventoryResource::collection($inventories)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'stock_quantity' => 'required|integer|min:0',
            'reorder_level' => 'sometimes|integer|min:0',
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);
        $product->update(['stock_quantity' => $validated['stock_quantity']]);

        $inventory = Inventory::query()
            ->where('product_id', $product->id)
            ->firstOrFail();
        if (array_key_exists('reorder_level', $validated)) {
            $inventory->update(['reorder_level' => $validated['reorder_level']]);
        }

        $response = (new InventoryResource($inventory->load('product.category')))->response();
        $response->setStatusCode(201);

        return $response;
    }

    public function show(Request $request, Inventory $inventory): JsonResponse
    {
        $this->authorizeAdmin($request);

        return (new InventoryResource($inventory->load('product.category')))->response();
    }

    public function update(Request $request, Inventory $inventory): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'stock_quantity' => 'sometimes|required|integer|min:0',
            'reorder_level' => 'sometimes|required|integer|min:0',
        ]);

        if (array_key_exists('stock_quantity', $validated)) {
            $product = Product::query()->findOrFail($inventory->product_id);
            $product->update([
                'stock_quantity' => $validated['stock_quantity'],
            ]);
        }

        if (array_key_exists('reorder_level', $validated)) {
            $inventory->update(['reorder_level' => $validated['reorder_level']]);
        }

        return (new InventoryResource(
            $inventory->refresh()->load('product.category')
        ))->response();
    }

    public function adjust(Request $request, Inventory $inventory): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'delta' => 'required|integer|between:-1000000,1000000|not_in:0',
        ]);

        DB::transaction(function () use ($inventory, $validated): void {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($inventory->product_id);
            $quantity = $lockedProduct->stock_quantity + $validated['delta'];
            abort_if($quantity < 0, 422, 'Stock cannot be reduced below zero.');

            $lockedProduct->update(['stock_quantity' => $quantity]);
        });

        return (new InventoryResource(
            $inventory->refresh()->load('product.category')
        ))->response();
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Admin only');
    }
}
