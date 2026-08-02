<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProductService
{
    public function create(array $validated): Product
    {
        return DB::transaction(function () use ($validated) {
            $product = Product::create([
                'configurable_id' => null,
                'type' => $validated['type'],
                'product_number' => $validated['product_number'] ?? null,
                'sku' => $validated['sku'],
                'price' => $validated['type'] === 'configurable'
                    ? $validated['price']
                    : 0,
                'special_price' => null,
                'special_price_from' => null,
                'special_price_to' => null,
                'use_default_tax' => true,
                'tax_id' => null,
                'is_new' => false,
                'is_featured' => false,
                'is_visible_individually' => true,
                'status' => false,
            ]);

            $product->translations()->createMany([
                [
                    'locale' => 'en',
                    'name' => $validated['product_name_en'],
                    'url_key' => $this->uniqueUrlKey(
                        $validated['product_name_en'],
                        'en'
                    ),
                ],
                [
                    'locale' => 'ar',
                    'name' => $validated['product_name_ar'],
                    'url_key' => $this->uniqueUrlKey(
                        $validated['product_name_ar'],
                        'ar'
                    ),
                ],
            ]);

            return $product;
        });
    }

    public function update(
        Product $product,
        array $validated
    ): Product {
        $storedImages = [];
        $filesToDelete = [];

        try {
            foreach ($validated['new_images'] ?? [] as $index => $image) {
                $path = $this->storeProductImage($image);
                $storedImages[$index] = $path;
            }

            $updatedProduct = DB::transaction(function () use (
                $product,
                $validated,
                $storedImages,
                &$filesToDelete
            ) {
                $this->updateCommonFields($product, $validated);

                if ($product->type === 'simple' && $product->configurable_id === null) {
                    $this->updateSimpleProduct(
                        $product,
                        $validated,
                        $storedImages,
                        $filesToDelete
                    );
                } elseif ($product->type === 'configurable' && $product->configurable_id === null) {
                    $this->updateConfigurableProduct(
                        $product,
                        $validated,
                        $storedImages,
                        $filesToDelete
                    );
                }

                return $product->refresh();
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles(array_values($storedImages));

            throw $exception;
        }

        $this->deleteStoredFiles($filesToDelete);

        return $updatedProduct;
    }

    public function generateVariants(
        Product $product,
        array $selections
    ): void {
        DB::transaction(function () use ($product, $selections) {
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedProduct->superAttributes()->exists() ||
                $lockedProduct->variants()->exists()
            ) {
                throw ValidationException::withMessages([
                    'super_attributes' => 'This configurable product has already been configured.',
                ]);
            }

            ksort($selections, SORT_NUMERIC);

            foreach ($selections as &$optionIds) {
                $optionIds = array_values(array_unique(array_map('intval', $optionIds)));
                sort($optionIds, SORT_NUMERIC);
            }
            unset($optionIds);

            $validAttributes = Attribute::query()
                ->whereIn('id', array_keys($selections))
                ->where('is_active', true)
                ->where('type', 'select')
                ->where('is_configurable', true)
                ->count();

            if ($validAttributes !== count($selections)) {
                throw ValidationException::withMessages([
                    'super_attributes' => 'Only active configurable select attributes may be used.',
                ]);
            }

            foreach ($selections as $attributeId => $optionIds) {
                if (AttributeOption::where('attribute_id', $attributeId)->whereIn('id', $optionIds)->count() !== count($optionIds)) {
                    throw ValidationException::withMessages([
                        'super_attributes.'.$attributeId => 'One or more options do not belong to this attribute.',
                    ]);
                }
            }

            $combinations = $this->cartesianProduct($selections);

            if (count($combinations) > 200) {
                throw ValidationException::withMessages([
                    'super_attributes' => 'A configurable product cannot exceed 200 combinations.',
                ]);
            }

            foreach ($selections as $attributeId => $optionIds) {
                $superAttribute = $lockedProduct->superAttributes()->create([
                    'attribute_id' => $attributeId,
                ]);
                $superAttribute->options()->sync($optionIds);
            }

            $combinationKeys = [];

            foreach ($combinations as $combination) {
                $combinationKey = implode('-', array_values($combination));

                if (isset($combinationKeys[$combinationKey])) {
                    throw ValidationException::withMessages([
                        'super_attributes' => 'A duplicate option combination was detected.',
                    ]);
                }

                $combinationKeys[$combinationKey] = true;
                $variant = $lockedProduct->variants()->create([
                    'type' => 'simple',
                    'product_number' => null,
                    'sku' => $this->uniqueVariantSku(
                        $lockedProduct->sku,
                        $combination
                    ),
                    'price' => $lockedProduct->price,
                    'special_price' => null,
                    'special_price_from' => null,
                    'special_price_to' => null,
                    'is_new' => false,
                    'is_featured' => false,
                    'is_visible_individually' => false,
                    'status' => false,
                ]);

                foreach ($combination as $attributeId => $optionId) {
                    $variant->attributeValues()->create([
                        'attribute_id' => $attributeId,
                        'attribute_option_id' => $optionId,
                        'locale' => null,
                        'value' => null,
                    ]);
                }
            }
        });
    }

    public function updateVariant(
        Product $variant,
        array $validated
    ): Product {
        $storedImages = [];
        $filesToDelete = [];

        try {
            foreach ($validated['new_images'] ?? [] as $index => $image) {
                $storedImages[$index] = $this->storeProductImage($image);
            }

            $updatedVariant = DB::transaction(function () use (
                $variant,
                $validated,
                $storedImages,
                &$filesToDelete
            ) {
                $variant->update([
                    'sku' => $validated['sku'],
                    'product_number' => $validated['product_number'] ?? null,
                    'price' => $validated['price'],
                    'special_price' => $validated['special_price'] ?? null,
                    'special_price_from' => $validated['special_price_from'] ?? null,
                    'special_price_to' => $validated['special_price_to'] ?? null,
                    'status' => $validated['status'],
                    'is_visible_individually' => false,
                ]);

                $this->syncImages(
                    $variant,
                    $validated,
                    $storedImages,
                    $filesToDelete
                );

                return $variant->refresh();
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles(array_values($storedImages));

            throw $exception;
        }

        $this->deleteStoredFiles($filesToDelete);

        return $updatedVariant;
    }

    public function delete(Product $product): void
    {
        $imagePaths = DB::transaction(function () use ($product) {
            $product = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $isReferenced = $product->orderItems()->exists()
                || $product->inventoryMovements()->exists()
                || $product->variants()->exists();

            if ($isReferenced) {
                throw ValidationException::withMessages([
                    'product' => 'This product is in use and cannot be deleted.',
                ]);
            }

            $imagePaths = $product->images()->pluck('path')->all();
            $product->delete();

            return $imagePaths;
        });

        $this->deleteStoredFiles($imagePaths);
    }

    public function createMissingVariant(
        Product $product,
        array $options
    ): Product {
        return DB::transaction(function () use ($product, $options) {
            $parent = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();
            $normalizedOptions = $this->validatedConfiguredOptions(
                $parent,
                $options
            );
            $combinationKey = $this->combinationKey($normalizedOptions);

            if ($this->variantCombinationExists($parent, $combinationKey)) {
                throw ValidationException::withMessages([
                    'options' => 'This variant combination already exists.',
                ]);
            }

            $superAttributes = $parent->superAttributes()->get()->keyBy('attribute_id');
            foreach ($normalizedOptions as $attributeId => $optionId) {
                $superAttributes->get($attributeId)->options()->syncWithoutDetaching([$optionId]);
            }

            return $this->createVariantRecord($parent, $normalizedOptions);
        });
    }

    public function bulkUpdateVariants(
        Product $product,
        array $validated
    ): void {
        $storedImages = [];
        $filesToDelete = [];

        try {
            if ($validated['action'] === 'add_images') {
                foreach ($validated['variants'] ?? [] as $variantId => $row) {
                    foreach ($row['images'] ?? [] as $image) {
                        $storedImages[$variantId][] = $this->storeProductImage($image);
                    }
                }
            }

            DB::transaction(function () use ($product, $validated, $storedImages, &$filesToDelete) {
                Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                $variants = Product::query()
                    ->with(['inventory', 'images'])
                    ->whereIn('id', $validated['variant_ids'])
                    ->where('configurable_id', $product->id)
                    ->where('type', 'simple')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($variants->count() !== count(array_unique($validated['variant_ids']))) {
                    throw ValidationException::withMessages([
                        'variant_ids' => 'Every selected variant must belong to this configurable product.',
                    ]);
                }

                foreach ($variants as $variantId => $variant) {
                    $row = $validated['variants'][$variantId] ?? [];
                    $action = $validated['action'];

                    if ($action === 'sku') {
                        $sku = $row['sku'];
                        if (Product::where('sku', $sku)->whereKeyNot($variantId)->exists()) {
                            throw ValidationException::withMessages(["variants.{$variantId}.sku" => 'This SKU is already in use.']);
                        }
                        $variant->update(['sku' => $sku, 'is_visible_individually' => false]);
                    } elseif ($action === 'prices') {
                        $price = $row['price'];
                        $specialPrice = $row['special_price'] ?? null;
                        if ($specialPrice !== null && (float) $specialPrice > (float) $price) {
                            throw ValidationException::withMessages(["variants.{$variantId}.special_price" => 'Special price cannot exceed price.']);
                        }
                        if (! empty($row['special_price_from']) && ! empty($row['special_price_to']) && strtotime($row['special_price_to']) < strtotime($row['special_price_from'])) {
                            throw ValidationException::withMessages(["variants.{$variantId}.special_price_to" => 'Special price end must be after or equal to its start.']);
                        }
                        $variant->update([
                            'price' => $price,
                            'special_price' => $specialPrice,
                            'special_price_from' => $row['special_price_from'] ?? null,
                            'special_price_to' => $row['special_price_to'] ?? null,
                            'is_visible_individually' => false,
                        ]);
                    } elseif ($action === 'status') {
                        $variant->update(['status' => $row['status'], 'is_visible_individually' => false]);
                    } elseif ($action === 'add_images') {
                        $sortOrder = ((int) $variant->images->max('sort_order')) + 1;
                        foreach ($storedImages[$variantId] ?? [] as $path) {
                            $variant->images()->create(['path' => $path, 'is_base' => false, 'sort_order' => $sortOrder++]);
                        }
                        $this->maintainBaseImage($variant);
                    } elseif ($action === 'remove_images') {
                        $filesToDelete = array_merge($filesToDelete, $variant->images->pluck('path')->all());
                        $variant->images()->delete();
                        $this->maintainBaseImage($variant);
                    } elseif ($action === 'remove_variants') {
                        if ($variant->inventoryMovements()->exists() || $variant->orderItems()->exists()) {
                            throw ValidationException::withMessages([
                                'variant_ids' => "Variant {$variant->sku} has protected inventory or transactional history. Disable it instead.",
                            ]);
                        }
                        $filesToDelete = array_merge($filesToDelete, $variant->images->pluck('path')->all());
                        $variant->delete();
                    }
                }
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles(collect($storedImages)->flatten()->all());

            if ($validated['action'] === 'remove_variants' && $exception instanceof QueryException) {
                throw ValidationException::withMessages([
                    'variant_ids' => 'One or more variants have protected transactional references. Disable them instead.',
                ]);
            }

            throw $exception;
        }

        $this->deleteStoredFiles($filesToDelete);
    }

    private function updateCommonFields(Product $product, array $validated): void
    {
        $product->update([
            'sku' => $validated['sku'],
            'product_number' => $validated['product_number'] ?? null,
        ]);

        foreach (['en', 'ar'] as $locale) {
            $product->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'name' => $validated['product_name_'.$locale],
                    'url_key' => $validated['url_key_'.$locale],
                    'short_description' => $validated['short_description_'.$locale] ?? null,
                    'description' => $validated['description_'.$locale] ?? null,
                    'meta_title' => $validated['meta_title_'.$locale] ?? null,
                    'meta_description' => $validated['meta_description_'.$locale] ?? null,
                    'meta_keywords' => $validated['meta_keywords_'.$locale] ?? null,
                ]
            );
        }
    }

    private function updateSimpleProduct(
        Product $product,
        array $validated,
        array $storedImages,
        array &$filesToDelete
    ): void {
        $useDefaultTax = (bool) ($validated['use_default_tax'] ?? $product->use_default_tax);
        $taxId = array_key_exists('tax_id', $validated)
            ? $validated['tax_id']
            : $product->tax_id;

        $product->update([
            'price' => $validated['price'],
            'special_price' => $validated['special_price'] ?? null,
            'special_price_from' => $validated['special_price_from'] ?? null,
            'special_price_to' => $validated['special_price_to'] ?? null,
            'use_default_tax' => $useDefaultTax,
            'tax_id' => $useDefaultTax
                ? null
                : $taxId,
            'is_new' => $validated['is_new'],
            'is_featured' => $validated['is_featured'],
            'is_visible_individually' => $validated['is_visible_individually'],
            'status' => $validated['status'],
        ]);

        $product->categories()->sync($validated['category_ids'] ?? []);
        if (array_key_exists('related_product_ids', $validated)) {
            $this->syncRelatedProducts($product, $validated['related_product_ids']);
        }
        $this->syncAttributeValues($product, $validated['attributes'] ?? []);
        $this->syncImages(
            $product,
            $validated,
            $storedImages,
            $filesToDelete
        );
    }

    private function syncRelatedProducts(Product $product, array $relatedProductIds): void
    {
        $relatedProductIds = array_values(array_map('intval', $relatedProductIds));

        if (count($relatedProductIds) !== count(array_unique($relatedProductIds))) {
            throw ValidationException::withMessages([
                'related_product_ids' => 'A related product may only be selected once.',
            ]);
        }

        if (in_array((int) $product->getKey(), $relatedProductIds, true)) {
            throw ValidationException::withMessages([
                'related_product_ids' => 'A product cannot be related to itself.',
            ]);
        }

        if ($relatedProductIds !== []) {
            $eligibleIds = Product::query()
                ->active()
                ->visible()
                ->where('type', 'simple')
                ->whereNull('configurable_id')
                ->whereIn('id', $relatedProductIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id) => (int) $id);

            if ($eligibleIds->count() !== count($relatedProductIds)) {
                throw ValidationException::withMessages([
                    'related_product_ids' => 'One or more selected related products are not eligible.',
                ]);
            }
        }

        $product->relatedProducts()->sync(
            collect($relatedProductIds)
                ->mapWithKeys(fn (int $relatedProductId, int $sortOrder) => [
                    $relatedProductId => ['sort_order' => $sortOrder],
                ])
                ->all()
        );
    }

    private function updateConfigurableProduct(
        Product $product,
        array $validated,
        array $storedImages,
        array &$filesToDelete
    ): void {
        $product->update([
            'price' => $validated['price'],
            'is_new' => $validated['is_new'],
            'is_featured' => $validated['is_featured'],
            'is_visible_individually' => $validated['is_visible_individually'],
            'status' => $validated['status'],
        ]);

        $product->categories()->sync($validated['category_ids'] ?? []);
        $this->syncAttributeValues($product, $validated['attributes'] ?? []);
        $this->syncImages(
            $product,
            $validated,
            $storedImages,
            $filesToDelete
        );
    }

    private function validatedConfiguredOptions(
        Product $product,
        array $options
    ): array {
        $superAttributes = $product->superAttributes()
            ->get()
            ->keyBy('attribute_id');
        $normalized = [];

        foreach ($options as $attributeId => $optionId) {
            $normalized[(int) $attributeId] = (int) $optionId;
        }

        ksort($normalized, SORT_NUMERIC);

        if (array_keys($normalized) !== $superAttributes->keys()->map(fn ($id) => (int) $id)->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'options' => 'Select exactly one option for every configurable attribute.',
            ]);
        }

        foreach ($normalized as $attributeId => $optionId) {
            $optionIsValid = AttributeOption::query()
                ->whereKey($optionId)
                ->where('attribute_id', $attributeId)
                ->exists();

            if (! $optionIsValid) {
                throw ValidationException::withMessages([
                    'options.'.$attributeId => 'The selected option does not belong to this configurable attribute.',
                ]);
            }
        }

        return $normalized;
    }

    private function combinationKey(array $options): string
    {
        ksort($options, SORT_NUMERIC);

        return collect($options)
            ->map(fn ($optionId, $attributeId) => $attributeId.':'.$optionId)
            ->implode('|');
    }

    private function variantCombinationExists(
        Product $product,
        string $combinationKey
    ): bool {
        return $product->variants()
            ->with('attributeValues:product_id,attribute_id,attribute_option_id')
            ->get()
            ->contains(function (Product $variant) use ($combinationKey) {
                $options = $variant->attributeValues
                    ->pluck('attribute_option_id', 'attribute_id')
                    ->all();

                return $this->combinationKey($options) === $combinationKey;
            });
    }

    private function createVariantRecord(
        Product $parent,
        array $options
    ): Product {
        $variant = $parent->variants()->create([
            'type' => 'simple',
            'product_number' => null,
            'sku' => $this->uniqueVariantSku(
                $parent->sku,
                $options
            ),
            'price' => $parent->price,
            'special_price' => null,
            'special_price_from' => null,
            'special_price_to' => null,
            'is_new' => false,
            'is_featured' => false,
            'is_visible_individually' => false,
            'status' => false,
        ]);

        foreach ($options as $attributeId => $optionId) {
            $variant->attributeValues()->create([
                'attribute_id' => $attributeId,
                'attribute_option_id' => $optionId,
                'locale' => null,
                'value' => null,
            ]);
        }

        return $variant;
    }

    private function syncAttributeValues(Product $product, array $values): void
    {
        $attributes = Attribute::query()
            ->where('is_active', true)
            ->when(
                $product->type !== 'simple' || $product->configurable_id !== null,
                fn ($query) => $query->where('is_configurable', false)
            )
            ->get()
            ->keyBy('id');

        $product->attributeValues()
            ->whereIn('attribute_id', $attributes->keys())
            ->delete();

        foreach ($values as $attributeId => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $attribute = $attributes->get($attributeId);

            if (! $attribute) {
                continue;
            }

            foreach ((array) $value as $item) {
                $product->attributeValues()->create([
                    'attribute_id' => $attributeId,
                    'attribute_option_id' => in_array($attribute->type, ['select', 'multiselect'])
                        ? $item
                        : null,
                    'locale' => null,
                    'value' => $attribute->type === 'text' ? $item : null,
                ]);
            }
        }
    }

    private function syncImages(
        Product $product,
        array $validated,
        array $storedImages,
        array &$filesToDelete
    ): void {
        $deletedIds = $validated['deleted_image_ids'] ?? [];

        if (! empty($deletedIds)) {
            $deletedImages = $product->images()->whereIn('id', $deletedIds)->get();
            $filesToDelete = array_merge($filesToDelete, $deletedImages->pluck('path')->all());
            $product->images()->whereIn('id', $deletedIds)->delete();
        }

        foreach ($validated['existing_image_sort_orders'] ?? [] as $imageId => $sortOrder) {
            $product->images()->whereKey($imageId)->update(['sort_order' => $sortOrder]);
        }

        foreach ($storedImages as $index => $path) {
            $product->images()->create([
                'path' => $path,
                'is_base' => false,
                'sort_order' => $validated['new_image_sort_orders'][$index] ?? 0,
            ]);
        }

        $baseImage = $validated['base_image'] ?? null;
        $selectedBaseImageId = null;

        if (is_string($baseImage) && str_starts_with($baseImage, 'existing:')) {
            $selectedBaseImageId = $product->images()
                ->whereKey((int) substr($baseImage, 9))
                ->value('id');
        } elseif (is_string($baseImage) && str_starts_with($baseImage, 'new:')) {
            $index = substr($baseImage, 4);
            $path = $storedImages[$index] ?? null;

            if ($path) {
                $selectedBaseImageId = $product->images()
                    ->where('path', $path)
                    ->value('id');
            }
        }

        $this->maintainBaseImage($product, $selectedBaseImageId);
    }

    private function maintainBaseImage(
        Product $product,
        ?int $selectedBaseImageId = null
    ): void {
        $images = $product->images()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'is_base']);

        if ($images->isEmpty()) {
            return;
        }

        $selectedImage = $selectedBaseImageId
            ? $images->firstWhere('id', $selectedBaseImageId)
            : null;
        $existingBaseImages = $images->where('is_base', true);
        $baseImage = $selectedImage
            ?? ($existingBaseImages->count() === 1
                ? $existingBaseImages->first()
                : $images->first());

        $product->images()->update(['is_base' => false]);
        $product->images()->whereKey($baseImage->id)->update(['is_base' => true]);
    }

    private function storeProductImage(UploadedFile $image): string
    {
        $path = $image->store('products/images', 'public');

        if (! is_string($path) || trim($path) === '') {
            throw new RuntimeException('The product image could not be stored.');
        }

        return $path;
    }

    private function deleteStoredFiles(array $paths): void
    {
        $paths = array_values(array_filter($paths));

        if (! empty($paths)) {
            Storage::disk('public')->delete($paths);
        }
    }

    private function cartesianProduct(array $selections): array
    {
        $combinations = [[]];

        foreach ($selections as $attributeId => $optionIds) {
            $nextCombinations = [];

            foreach ($combinations as $combination) {
                foreach ($optionIds as $optionId) {
                    $nextCombinations[] = $combination + [
                        $attributeId => $optionId,
                    ];
                }
            }

            $combinations = $nextCombinations;
        }

        return $combinations;
    }

    private function uniqueVariantSku(
        string $parentSku,
        array $options
    ): string {
        ksort($options, SORT_NUMERIC);
        $codes = AttributeOption::query()
            ->whereIn('id', array_values($options))
            ->pluck('code', 'id');
        $optionKey = collect($options)
            ->map(fn ($optionId) => $codes->get($optionId) ?? 'option-'.$optionId)
            ->implode('-');
        $optionSuffix = '-'.$optionKey;

        if (mb_strlen($optionSuffix) > 200) {
            $optionSuffix = '-'.hash('sha256', $optionKey);
        }

        $baseSku = mb_substr(
            $parentSku,
            0,
            255 - mb_strlen($optionSuffix)
        ).$optionSuffix;
        $sku = $baseSku;
        $collisionSuffix = 2;

        while (Product::where('sku', $sku)->exists()) {
            $suffix = '-'.$collisionSuffix;
            $sku = mb_substr(
                $baseSku,
                0,
                255 - mb_strlen($suffix)
            ).$suffix;
            $collisionSuffix++;
        }

        return $sku;
    }

    private function uniqueUrlKey(string $name, string $locale): string
    {
        $baseUrlKey = mb_strtolower(trim($name));
        $baseUrlKey = preg_replace('/[^\p{L}\p{N}]+/u', '-', $baseUrlKey);
        $baseUrlKey = trim($baseUrlKey ?? '', '-');
        $baseUrlKey = $baseUrlKey !== '' ? $baseUrlKey : 'product';

        $urlKey = $baseUrlKey;
        $suffix = 2;

        while (
            ProductTranslation::where('locale', $locale)
                ->where('url_key', $urlKey)
                ->exists()
        ) {
            $suffixText = "-{$suffix}";
            $urlKey = mb_substr(
                $baseUrlKey,
                0,
                255 - mb_strlen($suffixText)
            ).$suffixText;
            $suffix++;
        }

        return $urlKey;
    }
}
