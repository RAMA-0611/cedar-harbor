<?php

namespace App\Http\Requests\Admin;

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
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'email' => ['sometimes', 'required', 'email', 'max:254'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'document_type' => ['sometimes', 'required', 'string', 'max:10'],
            'document_number' => ['sometimes', 'required', 'string', 'max:30'],
        ];
    }
}
