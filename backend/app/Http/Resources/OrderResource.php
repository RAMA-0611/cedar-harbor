<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $delivery = [
            'mode' => $this->delivery_mode,
        ];

        if ($this->delivery_mode === 'COORDINATED_SHIPPING') {
            $delivery['address'] = $this->delivery_address;
        } else {
            $delivery['address'] = null;
        }

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal' => Money::format((string) $this->subtotal, 2),
            'tax_total' => Money::format((string) $this->tax_total, 2),
            'total' => Money::format((string) $this->total, 2),
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
                'document_type' => $this->customer_document_type,
                'document_number' => $this->customer_document_number,
            ],
            'delivery' => $delivery,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
