<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurableProductDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_configurable_parent_displays_only_options_used_by_active_variants(): void
    {
        [$parent, $color, $red, $blue, $green] = $this->configuredProduct();
        $this->variant($parent, [$color->id => $red->id], stock: 4);
        $this->variant($parent, [$color->id => $blue->id], stock: 0);
        $this->variant($parent, [$color->id => $green->id], stock: 4, active: false);

        $this->get(route('shop.products.show', 'configurable-shirt'))
            ->assertOk()
            ->assertSee('Select options to view price')
            ->assertSee('Color')
            ->assertSee('Red')
            ->assertSee('Blue')
            ->assertDontSee('Green')
            ->assertSee('name="options['.$color->id.']"', false)
            ->assertSee('"in_stock":false', false);
    }

    public function test_variant_presentation_uses_variant_commerce_data_and_parent_content(): void
    {
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

    private function configuredProduct(): array
    {
        $color = Attribute::factory()->create([
            'type' => 'select',
            'is_configurable' => true,
            'is_active' => true,
        ]);
        $color->translations()->create([
            'locale' => 'en',
            'admin_name' => 'Color',
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
