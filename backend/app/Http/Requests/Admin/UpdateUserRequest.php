<?php

namespace App\Http\Requests\Admin;

use App\Rules\ColombianMobilePhone;
use App\Rules\DocumentNumberForType;
use App\Rules\PersonName;
use App\Rules\StrongPassword;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', new PersonName],
            'email' => [
                'sometimes',
                'required',
                'email:rfc',
                'max:254',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => ['sometimes', 'required', 'string', new StrongPassword],
            'role' => ['sometimes', 'required', Rule::in(['ADMIN', 'CUSTOMER'])],
            'phone' => ['sometimes', 'nullable', 'string', new ColombianMobilePhone],
            'document_type' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(DocumentNumberForType::DOCUMENT_TYPES))],
            'document_number' => ['sometimes', 'nullable', 'string', new DocumentNumberForType('document_type')],
        ];
    }
}
