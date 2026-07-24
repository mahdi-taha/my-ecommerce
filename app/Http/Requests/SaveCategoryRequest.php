<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Category|null $category */
        $category = $this->route('category');
        $english = $category?->translations()->where('locale', 'en')->first();
        $arabic = $category?->translations()->where('locale', 'ar')->first();

        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id'), Rule::notIn($category ? [$category->id] : [])],
            'position' => ['required', 'integer', 'min:0'], 'status' => ['required', 'boolean'],
            'logo' => ['nullable', 'image'], 'banner' => ['nullable', 'image'],
            'category_name_en' => ['required', 'string', 'max:255'],
            'category_slug_en' => ['required', 'string', 'max:255', Rule::unique('category_translations', 'slug')->where('locale', 'en')->ignore($english?->id)],
            'meta_title_en' => ['nullable', 'string', 'max:255'], 'meta_description_en' => ['nullable', 'string'], 'meta_keywords_en' => ['nullable', 'string'],
            'category_name_ar' => ['required', 'string', 'max:255'],
            'category_slug_ar' => ['required', 'string', 'max:255', Rule::unique('category_translations', 'slug')->where('locale', 'ar')->ignore($arabic?->id)],
            'meta_title_ar' => ['nullable', 'string', 'max:255'], 'meta_description_ar' => ['nullable', 'string'], 'meta_keywords_ar' => ['nullable', 'string'],
            'filterable_attributes' => ['nullable', 'array'],
            'filterable_attributes.*' => ['integer', 'distinct', Rule::exists('attributes', 'id')->where('is_filterable', true)],
        ];
    }
}
