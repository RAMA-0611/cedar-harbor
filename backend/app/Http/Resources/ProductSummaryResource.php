<?php

namespace App\Http\Resources;

use App\Services\Products\ProductImageService;
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

        $category = null;
        if ($this->relationLoaded('category') && $this->category !== null) {
            $category = [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ];
        }

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            // Eloquent decimal:2 / decimal:4 casts — keep as strings, never float.
            'unit_price' => (string) $this->price,
            'currency' => $this->currency,
            'available_quantity' => $available,
            'tax_rate' => (string) $this->tax_rate,
            'description' => $this->description,
            'brand' => $this->brand,
            'condition' => $this->condition,
            'unit' => $this->unit,
            'image_url' => ProductImageService::publicUrl($this->image_path),
            'category' => $category,
        ];
    }
}
