<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        $categoryId = $this->route('category')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:160', 'not_regex:/[<>]/'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($categoryId),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000', 'not_regex:/[<>]/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
