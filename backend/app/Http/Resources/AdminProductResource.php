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
            'price' => number_format((float) $this->price, 2, '.', ''),
            'currency' => $this->currency,
            'tax_rate' => number_format((float) $this->tax_rate, 4, '.', ''),
            'stock_quantity' => (int) $this->stock_quantity,
            'is_active' => (bool) $this->is_active,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
