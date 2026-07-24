<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variant = $this->route('variant');

        return [
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->ignore($variant->id),
            ],
            'product_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'product_number')->ignore($variant->id),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'special_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'special_price_from' => ['nullable', 'date'],
            'special_price_to' => ['nullable', 'date', 'after_or_equal:special_price_from'],
            'status' => ['required', 'boolean'],
            'new_images' => ['nullable', 'array'],
            'new_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'new_image_sort_orders' => ['nullable', 'array'],
            'new_image_sort_orders.*' => ['integer', 'min:0'],
            'existing_image_sort_orders' => ['nullable', 'array'],
            'existing_image_sort_orders.*' => ['integer', 'min:0'],
            'deleted_image_ids' => ['nullable', 'array'],
            'deleted_image_ids.*' => ['integer', 'distinct'],
            'base_image' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $variant = $this->route('variant');
                $imageIds = $variant->images()->pluck('id')->map(fn ($id) => (int) $id);
                $deletedIds = collect((array) $this->input('deleted_image_ids', []))
                    ->map(fn ($id) => (int) $id);
                $sortOrderIds = collect(array_keys(
                    (array) $this->input('existing_image_sort_orders', [])
                ))->map(fn ($id) => (int) $id);

                if ($deletedIds->diff($imageIds)->isNotEmpty()) {
                    $validator->errors()->add(
                        'deleted_image_ids',
                        'One or more selected images do not belong to this variant.'
                    );
                }

                if ($sortOrderIds->diff($imageIds)->isNotEmpty()) {
                    $validator->errors()->add(
                        'existing_image_sort_orders',
                        'One or more selected images do not belong to this variant.'
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
}
