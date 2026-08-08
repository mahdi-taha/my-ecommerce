<?php

namespace App\Http\Requests;

use App\Enums\AttributeSwatchType;
use App\Models\AttributeOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAttributeOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'swatch_type' => ['required', Rule::enum(AttributeSwatchType::class)],
            'options' => ['required', 'array', 'min:1'],
            'options.*.id' => ['nullable', 'integer', 'distinct'],
            'options.*.code' => ['nullable', 'string', 'max:255'],
            'options.*.label_en' => ['required', 'string', 'max:255'],
            'options.*.label_ar' => ['required', 'string', 'max:255'],
            'options.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'options.*.swatch_value' => [
                Rule::requiredIf(fn (): bool => $this->input('swatch_type') === AttributeSwatchType::Color->value),
                'nullable',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'deleted_options' => ['nullable', 'array'],
            'deleted_options.*' => ['integer', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $attribute = $this->route('attribute');
            foreach ((array) $this->input('options') as $index => $row) {
                $id = isset($row['id']) ? (int) $row['id'] : null;
                if ($id && ! AttributeOption::whereKey($id)->where('attribute_id', $attribute->id)->exists()) {
                    $validator->errors()->add("options.{$index}.id", 'The option does not belong to this attribute.');
                }
            }
            $deleted = collect((array) $this->input('deleted_options'))->map(fn ($id) => (int) $id);
            $owned = AttributeOption::where('attribute_id', $attribute->id)->whereIn('id', $deleted)->count();
            if ($owned !== $deleted->unique()->count()) {
                $validator->errors()->add('deleted_options', 'One or more options do not belong to this attribute.');
            }
        }];
    }
}
