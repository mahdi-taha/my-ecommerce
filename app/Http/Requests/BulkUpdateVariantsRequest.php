<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkUpdateVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in([
                'sku', 'prices', 'status',
                'add_images', 'remove_images', 'remove_variants',
            ])],
            'variant_ids' => ['required', 'array', 'min:1'],
            'variant_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'variants' => ['nullable', 'array'],
            'variants.*.sku' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.special_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.special_price_from' => ['nullable', 'date'],
            'variants.*.special_price_to' => ['nullable', 'date'],
            'variants.*.status' => ['nullable', 'boolean'],
            'variants.*.images' => ['nullable', 'array'],
            'variants.*.images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $product = $this->route('product');
                if (! $product instanceof Product || $product->type !== 'configurable' || $product->configurable_id !== null) {
                    $validator->errors()->add('variant_ids', 'The selected product is not configurable.');

                    return;
                }

                $variantIds = collect((array) $this->input('variant_ids', []))->map(fn ($id) => (int) $id)->unique();
                $ownedCount = Product::query()
                    ->whereIn('id', $variantIds)
                    ->where('configurable_id', $product->id)
                    ->where('type', 'simple')
                    ->count();

                if ($ownedCount !== $variantIds->count()) {
                    $validator->errors()->add('variant_ids', 'Every selected variant must belong to this configurable product.');

                    return;
                }

                $action = $this->input('action');
                if (in_array($action, ['remove_images', 'remove_variants'], true)) {
                    return;
                }

                $rows = (array) $this->input('variants', []);
                $submittedSkus = [];
                foreach ($variantIds as $variantId) {
                    $row = (array) ($rows[$variantId] ?? []);
                    if ($action === 'sku' && trim((string) ($row['sku'] ?? '')) === '') {
                        $validator->errors()->add("variants.{$variantId}.sku", 'SKU is required.');
                    }
                    if ($action === 'sku') {
                        $sku = trim((string) ($row['sku'] ?? ''));
                        if ($sku !== '' && in_array($sku, $submittedSkus, true)) {
                            $validator->errors()->add("variants.{$variantId}.sku", 'Every selected variant must have a unique SKU.');
                        }
                        $submittedSkus[] = $sku;
                    }
                    if ($action === 'prices' && (! array_key_exists('price', $row) || $row['price'] === '')) {
                        $validator->errors()->add("variants.{$variantId}.price", 'Price is required.');
                    }
                    if ($action === 'status' && ! array_key_exists('status', $row)) {
                        $validator->errors()->add("variants.{$variantId}.status", 'Status is required.');
                    }
                }

                if ($action === 'add_images' && empty($this->allFiles()['variants'] ?? [])) {
                    $validator->errors()->add('variants', 'Select at least one image to add.');
                }
            },
        ];
    }
}
