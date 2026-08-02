<?php

namespace App\Http\Requests\Admin;

use App\Enums\HomepageBannerPlacement;
use App\Rules\SafeStorefrontContentUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHomepageBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        $rules = ['placement' => ['required', Rule::enum(HomepageBannerPlacement::class)], 'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'is_active' => ['nullable', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0']];
        foreach (['en', 'ar'] as $locale) {
            $rules["title_{$locale}"] = ['required', 'string', 'max:255'];
            $rules["eyebrow_{$locale}"] = ['nullable', 'string', 'max:255'];
            $rules["body_{$locale}"] = ['nullable', 'string', 'max:1000'];
            $rules["button_label_{$locale}"] = ['nullable', 'string', 'max:100'];
            $rules["link_url_{$locale}"] = ['nullable', 'string', 'max:2048', new SafeStorefrontContentUrl];
            $rules["image_alt_{$locale}"] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }
}
