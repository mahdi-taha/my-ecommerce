<?php

namespace App\Http\Requests;

use App\Enums\AttributeSwatchType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attribute_name_en' => ['required', 'string', 'max:255'],
            'attribute_name_ar' => ['required', 'string', 'max:255'],
            'attribute_sort_order' => ['nullable', 'integer', 'min:0'],
            'attribute_swatch_type' => ['nullable', Rule::enum(AttributeSwatchType::class)],
            'is_required' => ['required', 'boolean'],
            'is_configurable' => ['required', 'boolean'],
            'is_filterable' => ['required', 'boolean'],
            'is_visible_on_front' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'attribute_code' => ['prohibited'],
            'attribute_type' => ['prohibited'],
        ];
    }
}
