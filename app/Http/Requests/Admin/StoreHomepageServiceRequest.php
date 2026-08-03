<?php

namespace App\Http\Requests\Admin;

use App\Enums\HomepageServiceIcon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHomepageServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        $rules = [
            'icon' => ['required', Rule::enum(HomepageServiceIcon::class)],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];

        foreach (['en', 'ar'] as $locale) {
            $rules["title_{$locale}"] = ['required', 'string', 'max:120'];
            $rules["description_{$locale}"] = ['required', 'string', 'max:500'];
        }

        return $rules;
    }
}
