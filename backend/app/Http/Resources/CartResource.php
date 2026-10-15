<?php

namespace App\Http\Resources;

use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Cart */
class CartResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CartService $service */
        $service = app(CartService::class);
        $totals = $service->calculateTotals($this->resource);

        return [
            'id' => $this->id,
            'items' => $totals['items'],
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'currency' => $this->currency,
            'expires_at' => optional($this->expires_at)?->toISOString(),
        ];
    }
}
