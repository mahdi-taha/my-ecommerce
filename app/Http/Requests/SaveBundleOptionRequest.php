<?php

namespace App\Http\Requests;

use App\Enums\BundleOptionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBundleOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['required', 'string', 'max:255'],
            'type' => [
                'required',
                Rule::enum(BundleOptionType::class),
            ],
            'is_required' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'min_selections' => [
                'nullable',
                'required_if:type,checkbox,multiselect',
                'integer',
                'min:0',
            ],
            'max_selections' => [
                'nullable',
                'required_if:type,checkbox,multiselect',
                'integer',
                'min:1',
                'gte:min_selections',
            ],
        ];
    }
}
