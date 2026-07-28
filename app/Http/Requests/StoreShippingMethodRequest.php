<?php

namespace App\Http\Requests;

use App\Enums\ShippingMethodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShippingMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'lowercase', 'alpha_dash:ascii', Rule::unique('shipping_methods', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ShippingMethodType::class)],
            'amount' => ['required', 'decimal:0,4', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
