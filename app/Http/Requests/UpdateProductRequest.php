<?php

namespace App\Http\Requests;

use App\Models\Attribute;
use App\Models\BundleOption;
use App\Models\BundleOptionItem;
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
        $isBundleParent = $product->type === 'bundle' &&
            $product->configurable_id === null;
        $isConfigurableParent = $product->type === 'configurable' &&
            $product->configurable_id === null;

        if (! $isStandaloneSimple && ! $isBundleParent && ! $isConfigurableParent) {
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
            'business_mode' => ['nullable', Rule::in(['b2b', 'b2c'])],
            'attributes' => ['nullable', 'array'],
        ]);

        if ($isStandaloneSimple) {
            $rules = array_merge($rules, [
                'price' => ['required', 'numeric', 'min:0'],
                'special_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
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
            $rules['price'] = ['required', 'numeric', 'min:0'];
        }

        if ($isBundleParent) {
            $rules = array_merge($rules, [
                'bundle_options' => ['nullable', 'array'],
                'bundle_options.*.id' => ['nullable', 'integer'],
                'bundle_options.*.deleted' => ['required', 'boolean'],
                'bundle_options.*.title_en' => ['nullable', 'string', 'max:255'],
                'bundle_options.*.title_ar' => ['nullable', 'string', 'max:255'],
                'bundle_options.*.type' => ['nullable', Rule::in(['select', 'radio', 'checkbox', 'multiselect'])],
                'bundle_options.*.is_required' => ['nullable', 'boolean'],
                'bundle_options.*.sort_order' => ['nullable', 'integer', 'min:0'],
                'bundle_options.*.min_selections' => ['nullable', 'integer', 'min:0'],
                'bundle_options.*.max_selections' => ['nullable', 'integer', 'min:1'],
                'bundle_options.*.items' => ['nullable', 'array'],
                'bundle_options.*.items.*.id' => ['nullable', 'integer'],
                'bundle_options.*.items.*.deleted' => ['required', 'boolean'],
                'bundle_options.*.items.*.product_id' => ['nullable', 'integer'],
                'bundle_options.*.items.*.default_quantity' => ['nullable', 'numeric', 'gt:0'],
                'bundle_options.*.items.*.is_default' => ['nullable', 'boolean'],
                'bundle_options.*.items.*.sort_order' => ['nullable', 'integer', 'min:0'],
                'bundle_options.*.items.*.price_override' => ['nullable', 'numeric', 'min:0'],
            ]);
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
                    in_array($product->type, ['simple', 'configurable', 'bundle']);

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

                if ($product->type === 'bundle') {
                    $this->validateBundleOptions($validator, $product);
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

    private function validateBundleOptions(Validator $validator, Product $product): void
    {
        foreach ((array) $this->input('bundle_options', []) as $optionKey => $optionData) {
            $optionData = (array) $optionData;
            $optionId = isset($optionData['id']) ? (int) $optionData['id'] : null;

            if ($optionId && ! BundleOption::whereKey($optionId)->where('product_id', $product->id)->exists()) {
                $validator->errors()->add("bundle_options.{$optionKey}.id", 'The bundle option does not belong to this product.');

                continue;
            }

            if ((bool) ($optionData['deleted'] ?? false)) {
                continue;
            }

            foreach (['title_en', 'title_ar', 'type', 'sort_order'] as $field) {
                if (! isset($optionData[$field]) || $optionData[$field] === '') {
                    $validator->errors()->add("bundle_options.{$optionKey}.{$field}", 'This field is required.');
                }
            }

            if (in_array($optionData['type'] ?? null, ['checkbox', 'multiselect'], true)) {
                $min = $optionData['min_selections'] ?? null;
                $max = $optionData['max_selections'] ?? null;
                if ($min === null || $max === null || (int) $max < (int) $min) {
                    $validator->errors()->add("bundle_options.{$optionKey}.max_selections", 'Maximum selections must be at least the minimum selections.');
                }
            }

            $activeProductIds = [];
            foreach ((array) ($optionData['items'] ?? []) as $itemKey => $itemData) {
                $itemData = (array) $itemData;
                $itemId = isset($itemData['id']) ? (int) $itemData['id'] : null;

                if ($itemId && (! $optionId || ! BundleOptionItem::whereKey($itemId)->where('bundle_option_id', $optionId)->exists())) {
                    $validator->errors()->add("bundle_options.{$optionKey}.items.{$itemKey}.id", 'The bundle item does not belong to this option.');

                    continue;
                }

                if ((bool) ($itemData['deleted'] ?? false)) {
                    continue;
                }

                $itemProductId = (int) ($itemData['product_id'] ?? 0);
                $eligible = Product::query()
                    ->whereKey($itemProductId)
                    ->where('type', 'simple')
                    ->where('status', true)
                    ->whereKeyNot($product->id)
                    ->exists();

                if (! $eligible) {
                    $validator->errors()->add("bundle_options.{$optionKey}.items.{$itemKey}.product_id", 'Select an active simple product or variant.');
                }

                if (in_array($itemProductId, $activeProductIds, true)) {
                    $validator->errors()->add("bundle_options.{$optionKey}.items.{$itemKey}.product_id", 'A product may appear only once in the same option.');
                }
                $activeProductIds[] = $itemProductId;
            }
        }
    }
}
