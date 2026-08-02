<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('q')) {
            $this->merge(['q' => trim((string) $this->input('q'))]);
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'category' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('status', true),
            ],
            'min_price' => ['nullable', 'decimal:0,4', 'min:0'],
            'max_price' => ['nullable', 'decimal:0,4', 'gte:min_price'],
            'stock' => ['nullable', Rule::in(['in'])],
            'sale' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'new' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in([
                'newest',
                'oldest',
                'price_asc',
                'price_desc',
                'name_asc',
                'name_desc',
            ])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
