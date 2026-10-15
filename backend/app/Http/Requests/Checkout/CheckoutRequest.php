<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutRequest extends FormRequest
{
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
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:160'],
            'customer.email' => ['required', 'email', 'max:254'],
            'customer.phone' => ['required', 'string', 'max:30'],
            'customer.document_type' => ['required', 'string', 'max:10'],
            'customer.document_number' => ['required', 'string', 'max:30'],
            'delivery' => ['required', 'array'],
            'delivery.mode' => ['required', 'in:PICKUP,COORDINATED_SHIPPING'],
            'delivery.address' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $this->input('delivery.mode') === 'COORDINATED_SHIPPING'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('delivery.mode') !== 'COORDINATED_SHIPPING') {
                return;
            }

            $address = $this->input('delivery.address');
            if (! is_string($address) || trim($address) === '' || mb_strlen(trim($address)) < 5) {
                $validator->errors()->add(
                    'delivery.address',
                    'A non-blank delivery address (min 5 characters) is required for coordinated shipping.',
                );
            }
        });
    }

    /**
     * Validated CheckoutRequest with PICKUP address normalized to null.
     *
     * @return array{customer: array<string, string>, delivery: array{mode: string, address: ?string}}
     */
    public function checkoutPayload(): array
    {
        /** @var array{customer: array<string, string>, delivery: array{mode: string, address?: ?string}} $data */
        $data = $this->validated();

        if ($data['delivery']['mode'] === 'PICKUP') {
            $data['delivery']['address'] = null;
        } else {
            $data['delivery']['address'] = trim((string) ($data['delivery']['address'] ?? ''));
        }

        return $data;
    }
}
