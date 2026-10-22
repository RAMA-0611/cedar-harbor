<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminUpdateOrderRequest extends FormRequest
{
    private const ALLOWED = [
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_document_type',
        'customer_document_number',
        'delivery_mode',
        'delivery_address',
        'status',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['sometimes', 'required', 'string', 'max:160'],
            'customer_email' => ['sometimes', 'required', 'email', 'max:254'],
            'customer_phone' => ['sometimes', 'required', 'string', 'max:30'],
            'customer_document_type' => ['sometimes', 'required', 'string', 'max:10'],
            'customer_document_number' => ['sometimes', 'required', 'string', 'max:30'],
            'delivery_mode' => ['sometimes', 'required', 'in:PICKUP,COORDINATED_SHIPPING'],
            'delivery_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in(['CANCELLED'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), self::ALLOWED) as $key) {
                $validator->errors()->add($key, 'This field is not allowed.');
            }

            $order = $this->currentOrder();
            $mode = $this->has('delivery_mode')
                ? $this->input('delivery_mode')
                : $order?->delivery_mode;
            $address = $this->has('delivery_address')
                ? $this->input('delivery_address')
                : $order?->delivery_address;

            if ($mode !== 'COORDINATED_SHIPPING') {
                return;
            }

            if (! is_string($address) || trim($address) === '' || mb_strlen(trim($address)) < 5) {
                $validator->errors()->add(
                    'delivery_address',
                    'A non-blank delivery address (min 5 characters) is required for coordinated shipping.',
                );
            }
        });
    }

    private function currentOrder(): ?Order
    {
        $orderId = $this->route('orderId');
        if (! is_string($orderId) || ! Str::isUuid($orderId)) {
            return null;
        }

        return Order::query()->whereKey($orderId)->first();
    }
}
