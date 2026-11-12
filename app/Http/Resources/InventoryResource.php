<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'stock_quantity' => $this->stock_quantity,
            'reorder_level' => $this->reorder_level,
            'updated_at' => $this->updated_at?->toISOString(),
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
