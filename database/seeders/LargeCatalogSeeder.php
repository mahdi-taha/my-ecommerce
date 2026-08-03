<?php

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ProductService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LargeCatalogSeeder extends Seeder
{
    protected const INVENTORY_ACTOR_EMAIL = null;

    protected const PREFIX = 'DEMO-LARGE';

    public function run(ProductService $products, InventoryService $inventory): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('LargeCatalogSeeder may run only in local or testing environments.');
        }

        fake()->seed(20260803);
        $configuration = $this->configuration();
        $actor = $this->inventoryActor();
        $attributes = $this->seedAttributes();
        $categories = $this->seedCategories($configuration);

        $this->attachFilterableAttributes($categories['roots'], $attributes);
        $this->seedSimpleProducts($configuration, $categories['assignable'], $attributes, $actor, $inventory);
        $this->seedConfigurableProducts(
            $configuration,
            $categories['assignable'],
            $attributes,
            $actor,
            $products,
            $inventory
        );

        $this->command?->info(sprintf(
            '[large-catalog] %d roots, %d children, %d grandchildren, %d simple products, %d configurable products',
            $configuration['root_categories'],
            $configuration['child_categories'],
            $configuration['third_level_categories'],
            $configuration['simple_products'],
            $configuration['configurable_products'],
        ));
    }

    /**
     * @return array{root_categories: int, child_categories: int, third_level_categories: int, simple_products: int, configurable_products: int, variants_per_configurable: int}
     */
    protected function configuration(): array
    {
        return [
            'root_categories' => 20,
            'child_categories' => 24,
            'third_level_categories' => 6,
            'simple_products' => 180,
            'configurable_products' => 15,
            'variants_per_configurable' => 4,
        ];
    }

    private function inventoryActor(): User
    {
        $configuredEmail = env('LARGE_CATALOG_ADMIN_EMAIL', static::INVENTORY_ACTOR_EMAIL);

        foreach (array_filter([$configuredEmail, 'test@example.com']) as $email) {
            $actor = User::query()
                ->admins()
                ->active()
                ->where('email', $email)
                ->first();

            if ($actor) {
                return $actor;
            }
        }

        $actor = User::query()->admins()->active()->orderBy('id')->first();

        if (! $actor) {
            throw new RuntimeException('LargeCatalogSeeder requires an active administrator for inventory attribution.');
        }

        return $actor;
    }

    /**
     * @return array<string, array{attribute: Attribute, options: Collection<int, AttributeOption>}>
     */
    private function seedAttributes(): array
    {
        $definitions = [
            'color' => [AttributeType::Select, true, 'Color', 'اللون', ['Black', 'White', 'Blue', 'Red', 'Green', 'Gold']],
            'storage' => [AttributeType::Select, true, 'Storage', 'التخزين', ['64 GB', '128 GB', '256 GB', '512 GB', '1 TB']],
            'size' => [AttributeType::Select, false, 'Size', 'المقاس', ['Small', 'Medium', 'Large', 'XL', 'XXL']],
            'material' => [AttributeType::Multiselect, false, 'Material', 'الخامة', ['Cotton', 'Leather', 'Metal', 'Wood', 'Glass', 'Recycled']],
        ];
        $result = [];

        DB::transaction(function () use ($definitions, &$result): void {
            foreach ($definitions as $key => [$type, $configurable, $english, $arabic, $optionLabels]) {
                $code = 'demo_large_'.$key;
                $attribute = Attribute::query()->where('code', $code)->first();

                if ($attribute) {
                    $this->assertAttributeIdentity($attribute, $type, $english);
                } else {
                    $attribute = Attribute::factory()
                        ->state(['code' => $code, 'sort_order' => count($result)])
                        ->when($type === AttributeType::Multiselect, fn ($factory) => $factory->multiselect())
                        ->when($type === AttributeType::Select, fn ($factory) => $factory->select())
                        ->filterable()
                        ->state(['is_configurable' => $configurable, 'is_required' => $configurable])
                        ->create();
                }

                $attribute->update([
                    'type' => $type->value,
                    'swatch_type' => 'dropdown',
                    'is_required' => $configurable,
                    'is_configurable' => $configurable,
                    'is_filterable' => true,
                    'is_visible_on_front' => true,
                    'is_active' => true,
                ]);
                $attribute->translations()->updateOrCreate(['locale' => 'en'], ['admin_name' => $english]);
                $attribute->translations()->updateOrCreate(['locale' => 'ar'], ['admin_name' => $arabic]);

                $options = collect();
                foreach ($optionLabels as $position => $label) {
                    $optionCode = $code.'_'.str($label)->slug('_');
                    $option = AttributeOption::query()->where('code', $optionCode)->first();

                    if ($option && (int) $option->attribute_id !== (int) $attribute->id) {
                        throw new RuntimeException("Large catalog option code collision: {$optionCode}.");
                    }

                    $option ??= AttributeOption::factory()->for($attribute)->ordered($position)->create(['code' => $optionCode]);
                    $existingEnglish = $option->translations()->where('locale', 'en')->value('label');

                    if ($existingEnglish !== null && $existingEnglish !== $label) {
                        throw new RuntimeException("Large catalog option identity collision: {$optionCode}.");
                    }

                    $option->update(['sort_order' => $position]);
                    $option->translations()->updateOrCreate(['locale' => 'en'], ['label' => $label]);
                    $option->translations()->updateOrCreate(['locale' => 'ar'], ['label' => $this->arabicOptionLabel($key, $position)]);
                    $options->push($option);
                }

                $result[$key] = ['attribute' => $attribute, 'options' => $options];
            }
        });

        return $result;
    }

    private function assertAttributeIdentity(Attribute $attribute, AttributeType $type, string $englishName): void
    {
        $storedName = $attribute->translations()->where('locale', 'en')->value('admin_name');

        if ($attribute->type !== $type->value || $storedName !== $englishName) {
            throw new RuntimeException("Large catalog attribute code collision: {$attribute->code}.");
        }
    }

    /**
     * @param  array<string, int>  $configuration
     * @return array{roots: Collection<int, Category>, assignable: Collection<int, Category>}
     */
    private function seedCategories(array $configuration): array
    {
        $roots = collect();
        $children = collect();
        $grandchildren = collect();

        DB::transaction(function () use ($configuration, $roots, $children, $grandchildren): void {
            for ($index = 1; $index <= $configuration['root_categories']; $index++) {
                $roots->push($this->upsertCategory(
                    sprintf('root-%02d', $index),
                    "Demo Category {$index}",
                    "فئة تجريبية {$index}",
                    null,
                    $index - 1,
                    0,
                    $index <= $configuration['root_categories'] - 2,
                ));
            }

            for ($index = 1; $index <= $configuration['child_categories']; $index++) {
                $parent = $roots[($index - 1) % min(8, $roots->count())];
                $children->push($this->upsertCategory(
                    sprintf('child-%02d', $index),
                    "Demo Subcategory {$index}",
                    "فئة فرعية تجريبية {$index}",
                    $parent,
                    intdiv($index - 1, min(8, $roots->count())),
                    1,
                    true,
                ));
            }

            for ($index = 1; $index <= $configuration['third_level_categories']; $index++) {
                $parent = $children[$index - 1];
                $grandchildren->push($this->upsertCategory(
                    sprintf('leaf-%02d', $index),
                    "Demo Leaf Category {$index}",
                    "فئة نهائية تجريبية {$index}",
                    $parent,
                    0,
                    2,
                    true,
                ));
            }
        });

        return [
            'roots' => $roots,
            'assignable' => $grandchildren->concat($children)->concat($roots->where('status', true))->values(),
        ];
    }

    private function upsertCategory(
        string $identity,
        string $englishName,
        string $arabicName,
        ?Category $parent,
        int $position,
        int $level,
        bool $active,
    ): Category {
        $englishSlug = strtolower(static::PREFIX).'-'.$identity;
        $arabicSlug = strtolower(static::PREFIX).'-ar-'.$identity;
        $translation = CategoryTranslation::query()->where('locale', 'en')->where('slug', $englishSlug)->first();
        $category = $translation?->category;

        if ($category && ($translation->name !== $englishName
            || (int) ($category->parent_id ?? 0) !== (int) ($parent?->id ?? 0)
            || (int) $category->level !== $level)) {
            throw new RuntimeException("Large catalog category slug collision: {$englishSlug}.");
        }

        $category ??= Category::factory()->create([
            'parent_id' => $parent?->id,
            'position' => $position,
            'level' => $level,
            'status' => $active,
        ]);
        $category->update([
            'parent_id' => $parent?->id,
            'position' => $position,
            'level' => $level,
            'logo_path' => null,
            'banner_path' => null,
            'status' => $active,
        ]);
        $category->translations()->updateOrCreate(['locale' => 'en'], ['name' => $englishName, 'slug' => $englishSlug]);
        $category->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $arabicName, 'slug' => $arabicSlug]);

        return $category;
    }

    /**
     * @param  Collection<int, Category>  $roots
     * @param  array<string, array{attribute: Attribute, options: Collection<int, AttributeOption>}>  $attributes
     */
    private function attachFilterableAttributes(Collection $roots, array $attributes): void
    {
        $attributeIds = collect($attributes)->pluck('attribute.id')->all();

        $roots->where('status', true)->each(
            fn (Category $category) => $category->filterableAttributes()->syncWithoutDetaching($attributeIds)
        );
    }

    /**
     * @param  array<string, int>  $configuration
     * @param  Collection<int, Category>  $categories
     * @param  array<string, array{attribute: Attribute, options: Collection<int, AttributeOption>}>  $attributes
     */
    private function seedSimpleProducts(
        array $configuration,
        Collection $categories,
        array $attributes,
        User $actor,
        InventoryService $inventory,
    ): void {
        for ($index = 1; $index <= $configuration['simple_products']; $index++) {
            $sku = sprintf('%s-SIMPLE-%03d', static::PREFIX, $index);
            $price = $index <= 3 ? 0 : 20 + (($index * 7) % 380);
            $product = $this->upsertProduct($sku, sprintf('%s-P-%04d', static::PREFIX, $index), ProductType::Simple, null, [
                'price' => $price,
                'is_new' => $index % 4 === 0,
                'is_featured' => $index % 5 === 0,
                'is_visible_individually' => true,
                'status' => true,
            ]);

            $this->applySaleWindow($product, $index, $price);
            $this->upsertProductTranslations($product, "Demo Product {$index}", "منتج تجريبي {$index}", 'simple-'.$index);
            $product->categories()->syncWithoutDetaching([$categories[($index - 1) % $categories->count()]->id]);
            $this->syncFacetValues($product, $attributes, $index);
            $this->seedInventory($product, $index, $actor, $inventory);
        }
    }

    /**
     * @param  array<string, int>  $configuration
     * @param  Collection<int, Category>  $categories
     * @param  array<string, array{attribute: Attribute, options: Collection<int, AttributeOption>}>  $attributes
     */
    private function seedConfigurableProducts(
        array $configuration,
        Collection $categories,
        array $attributes,
        User $actor,
        ProductService $products,
        InventoryService $inventory,
    ): void {
        $colorOptions = $attributes['color']['options']->take(2);
        $storageOptions = $attributes['storage']['options']->take(2);
        $selections = [
            $attributes['color']['attribute']->id => $colorOptions->pluck('id')->all(),
            $attributes['storage']['attribute']->id => $storageOptions->pluck('id')->all(),
        ];

        if ($configuration['variants_per_configurable'] !== 4) {
            throw new RuntimeException('LargeCatalogSeeder currently requires four variants per configurable Product.');
        }

        for ($index = 1; $index <= $configuration['configurable_products']; $index++) {
            $sku = sprintf('%s-CFG-%03d', static::PREFIX, $index);
            $parent = $this->upsertProduct($sku, sprintf('%s-C-%04d', static::PREFIX, $index), ProductType::Configurable, null, [
                'price' => 100 + ($index * 10),
                'is_new' => $index % 3 === 0,
                'is_featured' => $index % 2 === 0,
                'is_visible_individually' => true,
                'status' => true,
            ]);
            $this->upsertProductTranslations($parent, "Demo Configurable Product {$index}", "منتج تجريبي متعدد الخيارات {$index}", 'configurable-'.$index);
            $parent->categories()->syncWithoutDetaching([$categories[($index - 1) % $categories->count()]->id]);

            if (! $parent->superAttributes()->exists() && ! $parent->variants()->exists()) {
                $products->generateVariants($parent, $selections);
            }

            $this->assertConfigurableIdentity($parent, $selections);
            $variants = $parent->variants()->with('attributeValues')->orderBy('id')->get();

            foreach ($variants as $variantIndex => $variant) {
                $variantSku = sprintf('%s-CFG-%03d-V%02d', static::PREFIX, $index, $variantIndex + 1);
                $collision = Product::query()->where('sku', $variantSku)->whereKeyNot($variant->id)->exists();

                if ($collision) {
                    throw new RuntimeException("Large catalog Product SKU collision: {$variantSku}.");
                }

                $price = 90 + ($index * 10) + ($variantIndex * 15);
                $variant->update([
                    'sku' => $variantSku,
                    'product_number' => sprintf('%s-C-%04d-V%02d', static::PREFIX, $index, $variantIndex + 1),
                    'price' => $price,
                    'special_price' => $variantIndex === 0 && $index % 3 === 0 ? $price * .85 : null,
                    'special_price_from' => $variantIndex === 0 && $index % 3 === 0 ? now()->subDay() : null,
                    'special_price_to' => $variantIndex === 0 && $index % 3 === 0 ? now()->addMonth() : null,
                    'is_visible_individually' => false,
                    'status' => true,
                ]);
                $this->seedInventory($variant, ($index * 10) + $variantIndex, $actor, $inventory);
            }
        }
    }

    /**
     * @param  array<int, array<int, int>>  $selections
     */
    private function assertConfigurableIdentity(Product $parent, array $selections): void
    {
        $parent->load(['superAttributes.options', 'variants.attributeValues']);
        $actualAttributes = $parent->superAttributes->pluck('attribute_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $expectedAttributes = collect(array_keys($selections))->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($actualAttributes !== $expectedAttributes || $parent->variants->count() !== 4) {
            throw new RuntimeException("Large catalog configurable identity collision: {$parent->sku}.");
        }

        $combinations = $parent->variants->map(fn (Product $variant) => $variant->attributeValues
            ->sortBy('attribute_id')
            ->pluck('attribute_option_id')
            ->implode('-'));

        if ($combinations->unique()->count() !== 4) {
            throw new RuntimeException("Large catalog configurable variant collision: {$parent->sku}.");
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function upsertProduct(
        string $sku,
        string $productNumber,
        ProductType $type,
        ?Product $parent,
        array $values,
    ): Product {
        $product = Product::query()->where('sku', $sku)->first();

        if ($product && ($product->product_number !== $productNumber
            || $product->type !== $type->value
            || (int) ($product->configurable_id ?? 0) !== (int) ($parent?->id ?? 0))) {
            throw new RuntimeException("Large catalog Product SKU collision: {$sku}.");
        }

        $product ??= Product::factory()->create([
            'sku' => $sku,
            'product_number' => $productNumber,
            'type' => $type->value,
            'configurable_id' => $parent?->id,
        ]);
        $product->update([
            ...$values,
            'sku' => $sku,
            'product_number' => $productNumber,
            'type' => $type->value,
            'configurable_id' => $parent?->id,
            'use_default_tax' => true,
            'tax_id' => null,
        ]);

        return $product;
    }

    private function upsertProductTranslations(Product $product, string $englishName, string $arabicName, string $identity): void
    {
        foreach ([
            'en' => [$englishName, strtolower(static::PREFIX).'-'.$identity],
            'ar' => [$arabicName, strtolower(static::PREFIX).'-ar-'.$identity],
        ] as $locale => [$name, $urlKey]) {
            $collision = ProductTranslation::query()
                ->where('locale', $locale)
                ->where('url_key', $urlKey)
                ->where('product_id', '!=', $product->id)
                ->exists();

            if ($collision) {
                throw new RuntimeException("Large catalog Product URL collision: {$locale}/{$urlKey}.");
            }

            $translation = $product->translations()->where('locale', $locale)->first();
            if ($translation && ! str_starts_with($translation->url_key, strtolower(static::PREFIX).'-')) {
                throw new RuntimeException("Large catalog Product translation collision: {$product->sku}/{$locale}.");
            }

            $product->translations()->updateOrCreate(['locale' => $locale], [
                'name' => $name,
                'url_key' => $urlKey,
                'short_description' => $locale === 'en' ? "Development catalog item {$englishName}." : "عنصر كتالوج تجريبي {$arabicName}.",
                'description' => $locale === 'en' ? "Deterministic development description for {$englishName}." : "وصف تطوير حتمي للمنتج {$arabicName}.",
            ]);
        }
    }

    private function applySaleWindow(Product $product, int $index, float|int $price): void
    {
        $sale = ['special_price' => null, 'special_price_from' => null, 'special_price_to' => null];

        if ($price > 0 && $index % 7 === 0) {
            $sale = ['special_price' => $price * .8, 'special_price_from' => now()->subDay(), 'special_price_to' => now()->addMonth()];
        } elseif ($price > 0 && $index % 20 === 0) {
            $sale = ['special_price' => $price * .8, 'special_price_from' => now()->addWeek(), 'special_price_to' => now()->addMonth()];
        } elseif ($price > 0 && $index % 18 === 0) {
            $sale = ['special_price' => $price * .8, 'special_price_from' => now()->subMonth(), 'special_price_to' => now()->subWeek()];
        }

        $product->update($sale);
    }

    /**
     * @param  array<string, array{attribute: Attribute, options: Collection<int, AttributeOption>}>  $attributes
     */
    private function syncFacetValues(Product $product, array $attributes, int $index): void
    {
        $product->attributeValues()->delete();

        foreach ($attributes as $key => $definition) {
            $usableOptions = $definition['options']->slice(0, -1)->values();
            $option = $usableOptions[$index % $usableOptions->count()];
            $product->attributeValues()->create([
                'attribute_id' => $definition['attribute']->id,
                'attribute_option_id' => $option->id,
                'locale' => null,
                'value' => null,
            ]);

            if ($key === 'material' && $index % 3 === 0) {
                $second = $usableOptions[($index + 1) % $usableOptions->count()];
                $product->attributeValues()->create([
                    'attribute_id' => $definition['attribute']->id,
                    'attribute_option_id' => $second->id,
                    'locale' => null,
                    'value' => null,
                ]);
            }
        }
    }

    private function seedInventory(Product $product, int $index, User $actor, InventoryService $inventory): void
    {
        $desiredQuantity = match ($index % 7) {
            0 => 0,
            1 => 2,
            default => 12 + ($index % 30),
        };
        $threshold = $index % 7 === 1 ? 5 : 3;

        if (! $product->inventoryMovements()->exists()) {
            $inventory->setOpeningStock($product, [
                'quantity' => max(1, $desiredQuantity),
                'unit_cost' => max(1, (float) $product->price * .55),
                'notes' => 'Opening stock created by LargeCatalogSeeder.',
            ], (int) $actor->id);

            if ($desiredQuantity === 0) {
                $inventory->recordStockCount($product, [
                    'counted_quantity' => 0,
                    'notes' => 'Out-of-stock scenario created by LargeCatalogSeeder.',
                ], (int) $actor->id);
            }
        }

        $inventory->updateLowStockAlert($product, $threshold);
    }

    private function arabicOptionLabel(string $attribute, int $position): string
    {
        $labels = [
            'color' => ['أسود', 'أبيض', 'أزرق', 'أحمر', 'أخضر', 'ذهبي'],
            'storage' => ['64 جيجابايت', '128 جيجابايت', '256 جيجابايت', '512 جيجابايت', '1 تيرابايت'],
            'size' => ['صغير', 'متوسط', 'كبير', 'كبير جداً', 'كبير جداً جداً'],
            'material' => ['قطن', 'جلد', 'معدن', 'خشب', 'زجاج', 'معاد التدوير'],
        ];

        return $labels[$attribute][$position];
    }
}
