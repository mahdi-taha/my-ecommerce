<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('type', 'simple')],
            'quantity' => ['required', 'numeric', 'decimal:0,4', 'gt:0'],
            'unit_cost' => ['required', 'numeric', 'decimal:0,4', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
