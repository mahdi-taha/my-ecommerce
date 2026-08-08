<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Product;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConfigurableProductDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_configurable_parent_displays_only_options_used_by_active_variants(): void
    {
        [$parent, $color, $red, $blue, $green] = $this->configuredProduct();
        $this->variant($parent, [$color->id => $red->id], stock: 4);
        $this->variant($parent, [$color->id => $blue->id], stock: 0);
        $this->variant($parent, [$color->id => $green->id], stock: 4, active: false);

        $this->get(route('shop.products.show', 'configurable-shirt'))
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('$ 100.00')
            ->assertSee('Color')
            ->assertSee('Red')
            ->assertSee('Blue')
            ->assertDontSee('Green')
            ->assertSee('name="options['.$color->id.']"', false)
            ->assertSee('"in_stock":false', false);
    }

    public function test_variant_presentation_uses_variant_commerce_data_and_parent_content(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/red-shirt.jpg', 'image');
        [$parent, $color, $red] = $this->configuredProduct();
        $variant = $this->variant($parent, [$color->id => $red->id], stock: 3);
        $variant->update([
            'sku' => 'SHIRT-RED',
            'price' => 90,
            'special_price' => 75,
            'special_price_from' => now()->subDay(),
            'special_price_to' => now()->addDay(),
        ]);
        $variant->images()->create([
            'path' => 'products/red-shirt.jpg',
            'is_base' => true,
            'sort_order' => 0,
        ]);

        $this->get(route('shop.products.show', 'configurable-shirt'))
            ->assertOk()
            ->assertSee('Configured Shirt')
            ->assertSee('SHIRT-RED', false)
            ->assertSee('$ 75.00', false)
            ->assertSee('$ 90.00', false)
            ->assertSee('red-shirt.jpg', false);
    }

    public function test_color_swatch_uses_native_radio_safe_color_and_visible_localized_name(): void
    {
        [$parent, $color, $red] = $this->configuredProduct();
        $color->update(['swatch_type' => 'color']);
        $red->update(['swatch_value' => '#aa0000']);
        $this->variant($parent, [$color->id => $red->id], stock: 4);

        $this->get(route('shop.products.show', 'configurable-shirt'))
            ->assertOk()
            ->assertSee('type="radio"', false)
            ->assertSee('name="options['.$color->id.']"', false)
            ->assertSee('style="--storefront-swatch-color: #AA0000"', false)
            ->assertSee('aria-hidden="true"', false)
            ->assertSee('Red');
    }

    public function test_invalid_legacy_color_uses_safe_fallback_without_outputting_css(): void
    {
        [$parent, $color, $red] = $this->configuredProduct();
        $color->update(['swatch_type' => 'color']);
        $red->update(['swatch_value' => '#000000;background:url(evil)']);
        $this->variant($parent, [$color->id => $red->id], stock: 4);

        $this->get(route('shop.products.show', 'configurable-shirt'))
            ->assertOk()
            ->assertSee('storefront-configurable-option__swatch--missing', false)
            ->assertDontSee('background:url(evil)', false)
            ->assertSee('Red');
    }

    public function test_text_swatch_uses_a_labeled_native_radio_group(): void
    {
        [$parent, $color, $red] = $this->configuredProduct();
        $color->update(['swatch_type' => 'text']);
        $this->variant($parent, [$color->id => $red->id], stock: 4);

        $this->get(route('shop.products.show', 'configurable-shirt'))
            ->assertOk()
            ->assertSee('<fieldset', false)
            ->assertSee('<legend class="form-label fw-semibold">', false)
            ->assertSee('type="radio"', false)
            ->assertSee('for="configurable_attribute_'.$color->id.'_option_'.$red->id.'"', false);
    }

    public function test_null_swatch_falls_back_to_localized_dropdown(): void
    {
        [$parent, $color, $red] = $this->configuredProduct();
        $color->update(['swatch_type' => null]);
        $this->variant($parent, [$color->id => $red->id], stock: 4);

        $this->get(route('shop.products.show', 'configurable-shirt'))
            ->assertOk()
            ->assertSee('data-configurable-control="dropdown"', false)
            ->assertSee('<select', false)
            ->assertSee(__('shop.product_details.choose_attribute', ['attribute' => 'Color']));
    }

    public function test_arabic_swatch_keeps_localized_attribute_and_option_names_visible(): void
    {
        [$parent, $color, $red] = $this->configuredProduct();
        $color->update(['swatch_type' => 'color']);
        $red->update(['swatch_value' => '#FF0000']);
        $this->variant($parent, [$color->id => $red->id], stock: 4);

        $this->get(route('shop.products.show', [
            'locale' => 'ar',
            'url_key' => 'configurable-shirt-ar',
        ]))->assertOk()
            ->assertSee('اللون')
            ->assertSee('أحمر')
            ->assertSee('dir="rtl"', false);
    }

    public function test_configurable_attributes_use_configured_sort_order_then_id(): void
    {
        [$parent, $color, $red] = $this->configuredProduct();
        $color->update(['sort_order' => 20]);
        $size = Attribute::factory()->create([
            'type' => 'select',
            'swatch_type' => 'text',
            'is_configurable' => true,
            'is_active' => true,
            'sort_order' => 5,
        ]);
        $size->translations()->create(['locale' => 'en', 'admin_name' => 'Size']);
        $small = $this->option($size, 'small', 'Small');
        $sizeSuperAttribute = $parent->superAttributes()->create(['attribute_id' => $size->id]);
        $sizeSuperAttribute->options()->sync([$small->id]);
        $this->variant($parent, [
            $color->id => $red->id,
            $size->id => $small->id,
        ], stock: 4);

        $this->get(route('shop.products.show', 'configurable-shirt'))
            ->assertOk()
            ->assertSeeInOrder(['Size', 'Color']);
    }

    public function test_product_details_queries_do_not_grow_with_swatch_option_count(): void
    {
        [$parent, $color, $red, $blue] = $this->configuredProduct();
        $this->variant($parent, [$color->id => $red->id], stock: 4);
        $this->get(route('shop.products.show', 'configurable-shirt'))->assertOk();
        $phase = 'small';
        $counts = ['small' => 0, 'large' => 0];
        DB::listen(function (QueryExecuted $query) use (&$phase, &$counts): void {
            if (isset($counts[$phase])) {
                $counts[$phase]++;
            }
        });

        $this->get(route('shop.products.show', 'configurable-shirt'))->assertOk();
        $phase = 'setup';
        $this->variant($parent, [$color->id => $blue->id], stock: 4);
        $phase = 'large';
        $this->get(route('shop.products.show', 'configurable-shirt'))->assertOk();

        $this->assertSame($counts['small'], $counts['large']);
    }

    public function test_variant_images_use_valid_files_and_javascript_never_sets_an_empty_source(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/parent.jpg', 'image');
        Storage::disk('public')->put('products/valid-variant.jpg', 'image');
        [$parent, $color, $red] = $this->configuredProduct();
        $parent->images()->create([
            'path' => 'products/parent.jpg',
            'is_base' => true,
            'sort_order' => 0,
        ]);
        $variant = $this->variant($parent, [$color->id => $red->id], stock: 3);
        $variant->images()->createMany([
            ['path' => 'products/missing-variant.jpg', 'is_base' => true, 'sort_order' => 0],
            ['path' => 'products/valid-variant.jpg', 'is_base' => false, 'sort_order' => 1],
        ]);

        $this->get(route('shop.products.show', 'configurable-shirt'))
            ->assertOk()
            ->assertSee('products/parent.jpg', false)
            ->assertSee('valid-variant.jpg', false)
            ->assertDontSee('missing-variant.jpg', false)
            ->assertDontSee('src=""', false)
            ->assertDontSee('undefined', false);

        $script = file_get_contents(resource_path('js/shop/configurable-product.js'));
        $this->assertIsString($script);
        $this->assertStringContainsString("image.removeAttribute('src')", $script);
        $this->assertStringNotContainsString("image.setAttribute('src', imageUrl || '')", $script);
    }

    public function test_parent_without_an_eligible_variant_is_not_storefront_eligible(): void
    {
        [$parent, $color, $red] = $this->configuredProduct();
        $variant = $this->variant($parent, [$color->id => $red->id], stock: 5);
        $variant->update(['price' => 0]);

        $this->get(route('shop.products.show', 'configurable-shirt'))
            ->assertNotFound();
    }

    private function configuredProduct(): array
    {
        $color = Attribute::factory()->create([
            'type' => 'select',
            'swatch_type' => 'dropdown',
            'is_configurable' => true,
            'is_active' => true,
        ]);
        $color->translations()->create([
            'locale' => 'en',
            'admin_name' => 'Color',
        ]);
        $color->translations()->create([
            'locale' => 'ar',
            'admin_name' => 'اللون',
        ]);
        $red = $this->option($color, 'red', 'Red');
        $blue = $this->option($color, 'blue', 'Blue');
        $green = $this->option($color, 'green', 'Green');
        $parent = Product::factory()->create([
            'type' => ProductType::Configurable->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
            'sku' => 'SHIRT',
        ]);
        $parent->translations()->create([
            'locale' => 'en',
            'name' => 'Configured Shirt',
            'url_key' => 'configurable-shirt',
            'short_description' => 'Configured product description.',
        ]);
        $parent->translations()->create([
            'locale' => 'ar',
            'name' => 'قميص معد',
            'url_key' => 'configurable-shirt-ar',
            'short_description' => 'وصف المنتج المعد.',
        ]);
        $superAttribute = $parent->superAttributes()->create([
            'attribute_id' => $color->id,
        ]);
        $superAttribute->options()->sync([$red->id, $blue->id, $green->id]);

        return [$parent, $color, $red, $blue, $green];
    }

    private function option(
        Attribute $attribute,
        string $code,
        string $label
    ): AttributeOption {
        $option = $attribute->options()->create([
            'code' => $code,
            'sort_order' => $attribute->options()->count(),
        ]);
        $option->translations()->create([
            'locale' => 'en',
            'label' => $label,
        ]);
        $option->translations()->create([
            'locale' => 'ar',
            'label' => match ($code) {
                'red' => 'أحمر',
                'blue' => 'أزرق',
                default => 'أخضر',
            },
        ]);

        return $option;
    }

    private function variant(
        Product $parent,
        array $options,
        int $stock,
        bool $active = true
    ): Product {
        $variant = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => $parent->id,
            'status' => $active,
            'is_visible_individually' => false,
            'price' => 100,
        ]);

        foreach ($options as $attributeId => $optionId) {
            $variant->attributeValues()->create([
                'attribute_id' => $attributeId,
                'attribute_option_id' => $optionId,
            ]);
        }

        $variant->inventory()->create([
            'quantity' => $stock,
            'average_cost' => 20,
            'low_stock_alert' => 1,
        ]);

        return $variant;
    }
}
