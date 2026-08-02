<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCmsPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        $rules = ['is_active' => ['nullable', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0']];
        foreach (['en', 'ar'] as $locale) {
            $rules["title_{$locale}"] = ['required', 'string', 'max:255'];
            $rules["slug_{$locale}"] = ['required', 'string', 'max:255', 'regex:/^[^\/?#]+$/u'];
            $rules["body_{$locale}"] = ['nullable', 'string'];
            $rules["meta_title_{$locale}"] = ['nullable', 'string', 'max:255'];
            $rules["meta_description_{$locale}"] = ['nullable', 'string', 'max:1000'];
        }

        return $rules;
    }
}
