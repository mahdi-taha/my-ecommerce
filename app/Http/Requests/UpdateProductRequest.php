<?php

namespace App\Http\Requests;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $product = $this->route('product');

        if (
            $product instanceof Product
            && $product->type === 'simple'
            && $product->configurable_id === null
        ) {
            $this->merge([
                'related_product_ids' => $this->input('related_product_ids', []),
            ]);
        }
    }

    public function rules(): array
    {
        $product = $this->route('product');

        $englishTranslation = ProductTranslation::where('product_id', $product->id)
            ->where('locale', 'en')
            ->first();

        $arabicTranslation = ProductTranslation::where('product_id', $product->id)
            ->where('locale', 'ar')
            ->first();

        $rules = [
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->ignore($product->id),
            ],
            'product_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'product_number')
                    ->ignore($product->id),
            ],
            'product_name_en' => ['required', 'string', 'max:255'],
            'product_name_ar' => ['required', 'string', 'max:255'],
            'url_key_en' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_translations', 'url_key')
                    ->where('locale', 'en')
                    ->ignore($englishTranslation?->id),
            ],
            'url_key_ar' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_translations', 'url_key')
                    ->where('locale', 'ar')
                    ->ignore($arabicTranslation?->id),
            ],
            'short_description_en' => ['nullable', 'string'],
            'short_description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'meta_title_en' => ['nullable', 'string', 'max:255'],
            'meta_title_ar' => ['nullable', 'string', 'max:255'],
            'meta_description_en' => ['nullable', 'string'],
            'meta_description_ar' => ['nullable', 'string'],
            'meta_keywords_en' => ['nullable', 'string'],
            'meta_keywords_ar' => ['nullable', 'string'],
        ];

        $isStandaloneSimple = $product->type === 'simple' &&
            $product->configurable_id === null;
        $isConfigurableParent = $product->type === 'configurable' &&
            $product->configurable_id === null;

        if (! $isStandaloneSimple && ! $isConfigurableParent) {
            return $rules;
        }

        $rules = array_merge($rules, [
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')],
            'new_images' => ['nullable', 'array'],
            'new_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'new_image_sort_orders' => ['nullable', 'array'],
            'new_image_sort_orders.*' => ['integer', 'min:0'],
            'existing_image_sort_orders' => ['nullable', 'array'],
            'existing_image_sort_orders.*' => ['integer', 'min:0'],
            'deleted_image_ids' => ['nullable', 'array'],
            'deleted_image_ids.*' => ['integer', 'distinct'],
            'base_image' => ['nullable', 'string'],
            'is_new' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'is_visible_individually' => ['required', 'boolean'],
            'status' => ['required', 'boolean'],
            'attributes' => ['nullable', 'array'],
        ]);

        if ($isStandaloneSimple) {
            $rules = array_merge($rules, [
                'price' => ['required', 'numeric', 'decimal:0,4', 'min:0'],
                'special_price' => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'lte:price'],
                'special_price_from' => ['nullable', 'date'],
                'special_price_to' => ['nullable', 'date', 'after_or_equal:special_price_from'],
                'use_default_tax' => ['required', 'boolean'],
                'tax_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('taxes', 'id')->where('status', true),
                ],
                'related_product_ids' => ['nullable', 'array'],
                'related_product_ids.*' => [
                    'integer',
                    'distinct',
                    Rule::exists('products', 'id')
                        ->where('type', 'simple')
                        ->whereNull('configurable_id')
                        ->where('status', true)
                        ->where('is_visible_individually', true)
                        ->whereNot('id', $product->getKey()),
                ],
            ]);
        }

        if ($isConfigurableParent) {
            $rules['price'] = ['required', 'numeric', 'decimal:0,4', 'min:0'];
        }

        $attributes = Attribute::query()
            ->where('is_active', true)
            ->when(
                ! $isStandaloneSimple,
                fn ($query) => $query->where('is_configurable', false)
            )
            ->get();

        foreach ($attributes as $attribute) {
            $key = 'attributes.'.$attribute->id;
            $presence = $attribute->is_required ? 'required' : 'nullable';

            if ($attribute->type === 'text') {
                $rules[$key] = [$presence, 'string'];
            } elseif ($attribute->type === 'select') {
                $rules[$key] = [
                    $presence,
                    'integer',
                    Rule::exists('attribute_options', 'id')
                        ->where('attribute_id', $attribute->id),
                ];
            } elseif ($attribute->type === 'multiselect') {
                $rules[$key] = [$presence, 'array'];
                $rules[$key.'.*'] = [
                    'integer',
                    'distinct',
                    Rule::exists('attribute_options', 'id')
                        ->where('attribute_id', $attribute->id),
                ];
            }
        }

        return $rules;
    }

    public function attributes(): array
    {
        return $this->attributeValidationLabels()->flatMap(function (string $label, int $attributeId) {
            return [
                'attributes.'.$attributeId => $label,
                'attributes.'.$attributeId.'.*' => $label,
            ];
        })->all();
    }

    public function messages(): array
    {
        return $this->attributeValidationLabels()->mapWithKeys(fn (string $label, int $attributeId) => [
            'attributes.'.$attributeId.'.required' => $label.' is required.',
        ])->all();
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $product = $this->route('product');

                $supportsCatalogEditing = $product->configurable_id === null &&
                    in_array($product->type, ['simple', 'configurable']);

                if (! $supportsCatalogEditing) {
                    return;
                }

                $allowedAttributeIds = Attribute::query()
                    ->where('is_active', true)
                    ->when(
                        $product->type !== 'simple',
                        fn ($query) => $query->where('is_configurable', false)
                    )
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id);
                $submittedAttributeIds = collect(array_keys(
                    (array) $this->input('attributes', [])
                ))->map(fn ($id) => (string) $id);

                if ($submittedAttributeIds->diff($allowedAttributeIds)->isNotEmpty()) {
                    $validator->errors()->add(
                        'attributes',
                        'One or more submitted attributes are invalid.'
                    );
                }

                if ($product->type === 'configurable') {
                    $requiredConfigurableIds = Attribute::query()
                        ->where('is_active', true)
                        ->where('type', 'select')
                        ->where('is_configurable', true)
                        ->where('is_required', true)
                        ->pluck('id');
                    $selectedSuperAttributeIds = $product->superAttributes()->pluck('attribute_id');

                    if ($requiredConfigurableIds->diff($selectedSuperAttributeIds)->isNotEmpty()) {
                        $validator->errors()->add(
                            'attributes',
                            'Every required configurable attribute must be configured before this product can be saved.'
                        );
                    }
                }

                $imageIds = $product->images()->pluck('id')->map(fn ($id) => (int) $id);
                $deletedIds = collect((array) $this->input('deleted_image_ids', []))
                    ->map(fn ($id) => (int) $id);

                if ($deletedIds->diff($imageIds)->isNotEmpty()) {
                    $validator->errors()->add(
                        'deleted_image_ids',
                        'One or more selected images do not belong to this product.'
                    );
                }

                $sortOrderIds = collect(array_keys(
                    (array) $this->input('existing_image_sort_orders', [])
                ))->map(fn ($id) => (int) $id);

                if ($sortOrderIds->diff($imageIds)->isNotEmpty()) {
                    $validator->errors()->add(
                        'existing_image_sort_orders',
                        'One or more selected images do not belong to this product.'
                    );
                }

                $baseImage = $this->input('base_image');

                if (! $baseImage) {
                    return;
                }

                if (str_starts_with($baseImage, 'existing:')) {
                    $baseId = (int) substr($baseImage, 9);

                    if (! $imageIds->contains($baseId) || $deletedIds->contains($baseId)) {
                        $validator->errors()->add('base_image', 'The selected base image is invalid.');
                    }

                    return;
                }

                if (str_starts_with($baseImage, 'new:')) {
                    $index = substr($baseImage, 4);

                    if (! ctype_digit($index) || ! $this->hasFile('new_images.'.$index)) {
                        $validator->errors()->add('base_image', 'The selected base image is invalid.');
                    }

                    return;
                }

                $validator->errors()->add('base_image', 'The selected base image is invalid.');
            },
        ];
    }

    private function attributeValidationLabels(): Collection
    {
        return Attribute::query()
            ->with(['translations' => fn ($query) => $query->where('locale', 'en')])
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn (Attribute $attribute) => [
                $attribute->id => $attribute->translations->first()?->admin_name ?? $attribute->code,
            ]);
    }
}
