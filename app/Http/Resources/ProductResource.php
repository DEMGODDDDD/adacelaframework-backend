<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'description' => $this->description,
            'price' => (float) $this->price,
            'sale_price' => $this->sale_price ? (float) $this->sale_price : null,
            'final_price' => (float) $this->final_price,
            'stock_quantity' => $this->when(
                $this->resource->relationLoaded('inventory'),
                fn () => $this->inventory?->stock_quantity ?? $this->stock_quantity,
                $this->stock_quantity
            ),
            'in_stock' => $this->in_stock,
            'brand' => $this->brand,
            'status' => $this->status,
            'featured' => $this->featured,
            'image' => $this->whenLoaded('primaryImage', fn() => $this->primaryImage?->image),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'reviews_count' => $this->whenCounted('reviews'),
            'average_rating' => round((float) ($this->reviews_avg_rating ?? 0), 1),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}