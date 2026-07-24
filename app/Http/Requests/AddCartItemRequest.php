<?php

namespace App\Http\Requests;

use App\Enums\CartItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_type' => ['required', Rule::enum(CartItemType::class)],
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'options' => [
                Rule::requiredIf($this->input('product_type') === CartItemType::Configurable->value),
                'array',
            ],
            'options.*' => ['required', 'integer', 'distinct'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'product_type' => $this->input(
                'product_type',
                CartItemType::Simple->value
            ),
        ]);
    }
}
