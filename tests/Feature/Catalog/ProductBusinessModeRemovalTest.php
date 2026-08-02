<?php

namespace Tests\Feature\Catalog;

use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\Tax;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductBusinessModeRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_mode_column_and_admin_controls_are_removed(): void
    {
        $product = $this->product('Control Removal');

        $this->assertFalse(Schema::hasColumn('products', 'business_mode'));
        $this->assertNotContains('business_mode', $product->getFillable());

        $this->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertDontSee('Business Mode')
            ->assertDontSee('Use Global Tax Mode');
    }

    public function test_obsolete_submitted_value_is_ignored_during_product_update(): void
    {
        $product = $this->product('Ignored Business Mode');

        $this->actingAs(User::factory()->create(), 'admin')
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'business_mode' => 'b2b',
            ]))
            ->assertRedirect(route('admin.products.edit', $product));

        $this->assertSame('25.0000', $product->refresh()->price);
    }

    public function test_global_tax_mode_and_product_tax_selection_remain_authoritative(): void
    {
        $defaultTax = Tax::query()->create(['name' => 'Default', 'rate' => 10, 'status' => true]);
        $productTax = Tax::query()->create(['name' => 'Product', 'rate' => 5, 'status' => true]);
        $product = Product::factory()->create([
            'price' => 100,
            'use_default_tax' => true,
            'tax_id' => null,
        ]);

        $this->assertSame(100.0, $product->displayPrice('b2b', $defaultTax));
        $this->assertSame(110.0, $product->displayPrice('b2c', $defaultTax));
        $this->assertSame(10.0, $product->effectiveTaxRate($defaultTax));

        $product->update(['use_default_tax' => false, 'tax_id' => $productTax->id]);
        $product->setRelation('tax', $productTax);

        $this->assertSame(100.0, $product->displayPrice('b2b', $defaultTax));
        $this->assertSame(105.0, $product->displayPrice('b2c', $defaultTax));
        $this->assertSame(5.0, $product->effectiveTaxRate($defaultTax));
    }

    public function test_configurable_variant_generation_remains_functional(): void
    {
        $attribute = Attribute::factory()->create([
            'type' => 'select',
            'is_configurable' => true,
            'is_active' => true,
        ]);
        $option = $attribute->options()->create(['code' => 'red', 'sort_order' => 0]);
        $parent = Product::factory()->create([
            'type' => ProductType::Configurable->value,
            'price' => 25,
        ]);

        app(ProductService::class)->generateVariants($parent, [
            $attribute->id => [$option->id],
        ]);

        $variant = $parent->variants()->sole();
        $this->assertSame(ProductType::Simple->value, $variant->type);
        $this->assertSame('25.0000', $variant->price);
    }

    private function product(string $name): Product
    {
        $product = Product::factory()->create(['price' => 25]);
        $product->translations()->createMany([
            ['locale' => 'en', 'name' => $name, 'url_key' => str($name)->slug().'-en'],
            ['locale' => 'ar', 'name' => $name, 'url_key' => str($name)->slug().'-ar'],
        ]);

        return $product;
    }

    private function payload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'sku' => $product->sku,
            'product_number' => $product->product_number,
            'product_name_en' => $product->translations()->where('locale', 'en')->firstOrFail()->name,
            'product_name_ar' => $product->translations()->where('locale', 'ar')->firstOrFail()->name,
            'url_key_en' => $product->translations()->where('locale', 'en')->firstOrFail()->url_key,
            'url_key_ar' => $product->translations()->where('locale', 'ar')->firstOrFail()->url_key,
            'price' => $product->price,
            'special_price' => null,
            'use_default_tax' => true,
            'tax_id' => null,
            'related_product_ids' => [],
            'category_ids' => [],
            'attributes' => [],
            'is_new' => false,
            'is_featured' => false,
            'is_visible_individually' => true,
            'status' => true,
        ], $overrides);
    }
}
