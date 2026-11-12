<?php

namespace App\Http\Requests\Admin;

use App\Rules\ColombianMobilePhone;
use App\Rules\DocumentNumberForType;
use App\Rules\PersonName;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
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
        $customerId = $this->route('customer')?->id;

        return [
            'user_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('customers', 'user_id')->ignore($customerId),
            ],
            'name' => ['sometimes', 'required', 'string', new PersonName],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:254'],
            'phone' => ['sometimes', 'required', 'string', new ColombianMobilePhone],
            'document_type' => ['sometimes', 'required', 'string', Rule::in(array_keys(DocumentNumberForType::DOCUMENT_TYPES))],
            'document_number' => ['sometimes', 'required', 'string', new DocumentNumberForType('document_type')],
        ];
    }
}
