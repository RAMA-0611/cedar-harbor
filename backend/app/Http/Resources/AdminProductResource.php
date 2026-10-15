<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class AdminProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'brand' => $this->brand,
            'condition' => $this->condition,
            'unit' => $this->unit,
            // Eloquent decimal:2 / decimal:4 casts — keep as strings, never float.
            'price' => (string) $this->price,
            'currency' => $this->currency,
            'tax_rate' => (string) $this->tax_rate,
            'stock_quantity' => (int) $this->stock_quantity,
            'is_active' => (bool) $this->is_active,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
