<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'category_id' => ['required', 'uuid', 'exists:categories,id'],
            'sku' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*\z/', Rule::unique('products', 'sku')],
            'name' => ['required', 'string', 'min:3', 'max:255', 'not_regex:/[<>]/'],
            'description' => ['nullable', 'string', 'max:5000', 'not_regex:/[<>]/'],
            'brand' => ['nullable', 'string', 'max:120', 'not_regex:/[<>]/'],
            'condition' => ['nullable', 'string', 'max:40', 'not_regex:/[<>]/'],
            'unit' => ['nullable', 'string', 'max:60', 'not_regex:/[<>]/'],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'currency' => ['required', 'string', 'size:3', 'in:COP'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
