<?php

namespace App\Http\Requests\Checkout;

use App\Rules\ColombianMobilePhone;
use App\Rules\DocumentNumberForType;
use App\Rules\PersonName;

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
            'customer' => ['required', 'array:name,email,phone,document_type,document_number'],
            'customer.name' => ['required', 'string', new PersonName],
            'customer.email' => ['required', 'email:rfc', 'max:254'],
            'customer.phone' => ['required', 'string', new ColombianMobilePhone],
            'customer.document_type' => ['required', 'string', Rule::in(array_keys(DocumentNumberForType::DOCUMENT_TYPES))],
            'customer.document_number' => ['required', 'string', new DocumentNumberForType('customer.document_type')],
            'delivery' => ['required', 'array:mode,address'],
            'delivery.mode' => ['required', 'in:PICKUP,COORDINATED_SHIPPING'],
            'delivery.address' => [
                'nullable',
                'string',
                'min:5',
                'max:255',
                Rule::requiredIf(fn () => $this->input('delivery.mode') === 'COORDINATED_SHIPPING'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['customer', 'delivery']) as $key) {
                $validator->errors()->add($key, 'This field is not allowed.');
            }

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
