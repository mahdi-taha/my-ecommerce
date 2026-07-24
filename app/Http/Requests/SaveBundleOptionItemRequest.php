<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBundleOptionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bundleOption = $this->route('bundleOption');
        $bundleOptionItem = $this->route('bundleOptionItem');

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('type', 'simple')
                    ->where('status', true),
                Rule::unique('bundle_option_items', 'product_id')
                    ->where('bundle_option_id', $bundleOption->id)
                    ->ignore($bundleOptionItem?->id),
            ],
            'default_quantity' => ['required', 'numeric', 'gt:0'],
            'is_default' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
