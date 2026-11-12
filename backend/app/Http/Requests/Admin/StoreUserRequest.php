<?php

namespace App\Http\Requests\Admin;

use App\Rules\ColombianMobilePhone;
use App\Rules\DocumentNumberForType;
use App\Rules\PersonName;
use App\Rules\StrongPassword;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
            'name' => ['required', 'string', new PersonName],
            'email' => ['required', 'email:rfc', 'max:254', Rule::unique('users', 'email')],
            'password' => ['required', 'string', new StrongPassword],
            'role' => ['required', Rule::in(['ADMIN', 'CUSTOMER'])],
            // Un CUSTOMER necesita perfil completo para comprar.
            'phone' => ['required_if:role,CUSTOMER', 'nullable', 'string', new ColombianMobilePhone],
            'document_type' => ['required_if:role,CUSTOMER', 'nullable', 'string', Rule::in(array_keys(DocumentNumberForType::DOCUMENT_TYPES))],
            'document_number' => ['required_if:role,CUSTOMER', 'nullable', 'string', new DocumentNumberForType('document_type')],
        ];
    }
}
