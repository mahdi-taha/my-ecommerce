<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TopSellingProductListingRequest extends FormRequest
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
            'min_price' => ['nullable', 'decimal:0,4', 'min:0'],
            'max_price' => ['nullable', 'decimal:0,4', 'gte:min_price'],
            'stock' => ['nullable', Rule::in(['in'])],
            'sale' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'new' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['prohibited'],
            'category' => ['prohibited'],
            'attributes' => ['prohibited'],
        ];
    }
}
