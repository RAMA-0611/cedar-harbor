<?php

namespace App\Http\Requests\Admin;

use App\Rules\ColombianMobilePhone;
use App\Rules\DocumentNumberForType;
use App\Rules\PersonName;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
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
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('customers', 'user_id')],
            'name' => ['required', 'string', new PersonName],
            'email' => ['required', 'email:rfc', 'max:254'],
            'phone' => ['required', 'string', new ColombianMobilePhone],
            'document_type' => ['required', 'string', Rule::in(array_keys(DocumentNumberForType::DOCUMENT_TYPES))],
            'document_number' => ['required', 'string', new DocumentNumberForType('document_type')],
        ];
    }
}
