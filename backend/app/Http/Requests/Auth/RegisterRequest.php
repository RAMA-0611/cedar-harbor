<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\ColombianMobilePhone;
use App\Rules\DocumentNumberForType;
use App\Rules\PersonName;
use App\Rules\StrongPassword;
use Illuminate\Validation\Rule;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'email' => ['required', 'email:rfc', 'max:254', 'unique:users,email'],
            'phone' => ['required', 'string', new ColombianMobilePhone],
            'document_type' => ['required', 'string', Rule::in(array_keys(DocumentNumberForType::DOCUMENT_TYPES))],
            'document_number' => ['required', 'string', new DocumentNumberForType('document_type')],
            'password' => ['required', 'string', new StrongPassword, 'confirmed'],
        ];
    }
}
