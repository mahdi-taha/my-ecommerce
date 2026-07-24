<?php

namespace App\Http\Requests;

use App\Models\AttributeOption;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'options' => ['required', 'array', 'min:1'],
            'options.*' => ['required', 'integer', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $product = $this->route('product');

                if (! $product instanceof Product || $product->type !== 'configurable' || $product->configurable_id !== null) {
                    $validator->errors()->add('options', 'The selected product is not configurable.');

                    return;
                }

                $superAttributes = $product->superAttributes()
                    ->get()
                    ->keyBy('attribute_id');
                $options = (array) $this->input('options', []);
                $submittedAttributeIds = collect(array_keys($options))
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values();
                $requiredAttributeIds = $superAttributes->keys()
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values();

                if ($submittedAttributeIds->all() !== $requiredAttributeIds->all()) {
                    $validator->errors()->add(
                        'options',
                        'Select exactly one option for every configurable attribute.'
                    );

                    return;
                }

                foreach ($options as $attributeId => $optionId) {
                    $optionIsValid = AttributeOption::query()
                        ->whereKey($optionId)
                        ->where('attribute_id', $attributeId)
                        ->exists();

                    if (! $optionIsValid) {
                        $validator->errors()->add(
                            'options.'.$attributeId,
                            'The selected option does not belong to this configurable attribute.'
                        );
                    }
                }
            },
        ];
    }
}
