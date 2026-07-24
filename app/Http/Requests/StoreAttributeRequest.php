<?php

namespace App\Http\Requests;

use App\Enums\AttributeSwatchType;
use App\Enums\AttributeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['attribute_code' => strtolower(trim((string) $this->input('attribute_code')))]);
    }

    public function rules(): array
    {
        return [
            'attribute_code' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_-]+$/', Rule::unique('attributes', 'code')],
            'attribute_name_en' => ['required', 'string', 'max:255'],
            'attribute_name_ar' => ['required', 'string', 'max:255'],
            'attribute_sort_order' => ['nullable', 'integer', 'min:0'],
            'attribute_type' => ['required', Rule::enum(AttributeType::class)],
            'attribute_swatch_type' => ['nullable', Rule::enum(AttributeSwatchType::class)],
            'is_required' => ['required', 'boolean'],
            'is_configurable' => ['required', 'boolean'],
            'is_filterable' => ['required', 'boolean'],
            'is_visible_on_front' => ['required', 'boolean'],
        ];
    }
}
