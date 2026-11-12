<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        $productId = $this->route('product')?->id;

        return [
            'category_id' => ['sometimes', 'required', 'uuid', 'exists:categories,id'],
            'sku' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:64',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*\z/',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:255', 'not_regex:/[<>]/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000', 'not_regex:/[<>]/'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:120', 'not_regex:/[<>]/'],
            'condition' => ['sometimes', 'nullable', 'string', 'max:40', 'not_regex:/[<>]/'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:60', 'not_regex:/[<>]/'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'decimal:0,2'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'in:COP'],
            'tax_rate' => ['sometimes', 'required', 'numeric', 'min:0', 'max:1'],
            'stock_quantity' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
