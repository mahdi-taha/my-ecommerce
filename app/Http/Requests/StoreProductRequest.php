<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => [
                'required',
                Rule::enum(ProductType::class),
            ],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
            'product_number' => [
                'nullable',
                'string',
                'max:255',
                'unique:products,product_number',
            ],
            'product_name_en' => ['required', 'string', 'max:255'],
            'product_name_ar' => ['required', 'string', 'max:255'],
            'price' => [
                'nullable',
                'required_if:type,'.ProductType::Configurable->value,
                'numeric',
                'decimal:0,4',
                'min:0',
            ],
        ];
    }
}
