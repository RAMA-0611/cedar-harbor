<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reserved = (int) ($this->active_reserved_quantity ?? 0);
        $available = max(0, (int) $this->stock_quantity - $reserved);

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            // Eloquent decimal:2 / decimal:4 casts — keep as strings, never float.
            'unit_price' => (string) $this->price,
            'currency' => $this->currency,
            'available_quantity' => $available,
            'tax_rate' => (string) $this->tax_rate,
        ];
    }
}
