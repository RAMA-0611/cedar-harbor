<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OrderItem */
class OrderItemResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'product_name' => $this->product_name,
            'quantity' => (int) $this->quantity,
            'unit_price' => Money::format((string) $this->unit_price, 2),
            'tax_amount' => Money::format((string) $this->tax_amount, 2),
            'line_total' => Money::format((string) $this->line_total, 2),
        ];
    }
}
