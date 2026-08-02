<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\Tax;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductTaxRelatedProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_simple_product_uses_default_tax_and_has_no_related_products(): void
    {
        $product = app(ProductService::class)->create([
            'type' => 'simple',
            'sku' => 'SIMPLE-DEFAULT-TAX',
            'product_number' => null,
            'product_name_en' => 'Simple Product',
            'product_name_ar' => 'منتج بسيط',
        ]);

        $this->assertTrue($product->use_default_tax);
        $this->assertNull($product->tax_id);
        $this->assertFalse($product->relatedProducts()->exists());
    }

    public function test_product_specific_active_tax_can_be_saved_or_cleared(): void
    {
        $product = $this->product('Tax Product');
        $tax = Tax::query()->create([
            'name' => 'Standard Tax',
            'rate' => 11,
            'status' => true,
        ]);

        $this->actingAs(User::factory()->create(), 'admin')
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'use_default_tax' => false,
                'tax_id' => $tax->id,
            ]))
            ->assertRedirect(route('admin.products.edit', $product));

        $product->refresh();
        $this->assertFalse($product->use_default_tax);
        $this->assertSame($tax->id, $product->tax_id);

        $this->actingAs(User::factory()->create(), 'admin')
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'use_default_tax' => false,
                'tax_id' => null,
            ]))
            ->assertRedirect(route('admin.products.edit', $product));

        $this->assertNull($product->refresh()->tax_id);
    }

    public function test_edit_loads_active_taxes_and_existing_related_products(): void
    {
        $product = $this->product('Main Product');
        $related = $this->product('Related Product');
        $product->relatedProducts()->attach($related, ['sort_order' => 0]);
        $activeTax = Tax::query()->create(['name' => 'Active Tax', 'rate' => 5, 'status' => true]);
        $inactiveTax = Tax::query()->create(['name' => 'Inactive Tax', 'rate' => 7, 'status' => false]);

        $this->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertViewHas('taxes', fn ($taxes) => $taxes->contains($activeTax) && ! $taxes->contains($inactiveTax))
            ->assertViewHas(
                'selectedRelatedProductIds',
                fn (array $ids) => $ids === [(string) $related->id]
            )
            ->assertSee('Related Product')
            ->assertSee('selected', false);
    }

    public function test_related_products_are_added_removed_and_sorted_directionally(): void
    {
        $product = $this->product('Main Product');
        $removed = $this->product('Removed Product');
        $first = $this->product('First Product');
        $second = $this->product('Second Product');
        $product->relatedProducts()->attach($removed, ['sort_order' => 0]);

        $this->actingAs(User::factory()->create(), 'admin')
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'related_product_ids' => [$second->id, $first->id],
            ]))
            ->assertRedirect(route('admin.products.edit', $product));

        $this->assertDatabaseMissing('product_related_products', [
            'product_id' => $product->id,
            'related_product_id' => $removed->id,
        ]);
        $this->assertDatabaseHas('product_related_products', [
            'product_id' => $product->id,
            'related_product_id' => $second->id,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('product_related_products', [
            'product_id' => $product->id,
            'related_product_id' => $first->id,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseMissing('product_related_products', [
            'product_id' => $second->id,
            'related_product_id' => $product->id,
        ]);
    }

    public function test_self_duplicate_and_ineligible_related_products_are_rejected(): void
    {
        $product = $this->product('Main Product');
        $eligible = $this->product('Eligible Product');
        $hidden = $this->product('Hidden Product', ['is_visible_individually' => false]);

        $this->actingAs(User::factory()->create(), 'admin')
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'related_product_ids' => [$product->id],
            ]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('related_product_ids.0');

        $this->actingAs(User::factory()->create(), 'admin')
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'related_product_ids' => [$eligible->id, $eligible->id],
            ]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('related_product_ids.0');

        $this->actingAs(User::factory()->create(), 'admin')
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'related_product_ids' => [$hidden->id],
            ]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('related_product_ids.0');

        $this->assertFalse($product->relatedProducts()->exists());
    }

    public function test_inactive_tax_is_rejected(): void
    {
        $product = $this->product('Tax Product');
        $tax = Tax::query()->create([
            'name' => 'Inactive Tax',
            'rate' => 11,
            'status' => false,
        ]);

        $this->actingAs(User::factory()->create(), 'admin')
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'use_default_tax' => false,
                'tax_id' => $tax->id,
            ]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('tax_id');

        $this->assertTrue($product->refresh()->use_default_tax);
        $this->assertNull($product->tax_id);
    }

    public function test_relation_failure_rolls_back_product_changes(): void
    {
        $product = $this->product('Rollback Product', ['price' => 10]);

        try {
            app(ProductService::class)->update($product, $this->payload($product, [
                'price' => 99,
                'related_product_ids' => [999999],
            ]));
            $this->fail('An ineligible related product was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'One or more selected related products are not eligible.',
                $exception->errors()['related_product_ids'][0]
            );
        }

        $this->assertSame('10.0000', $product->refresh()->price);
        $this->assertFalse($product->relatedProducts()->exists());
    }

    private function product(string $name, array $attributes = []): Product
    {
        $product = Product::factory()->create($attributes);
        $product->translations()->createMany([
            [
                'locale' => 'en',
                'name' => $name,
                'url_key' => str($name)->slug().'-en-'.fake()->unique()->numberBetween(1, 999999),
            ],
            [
                'locale' => 'ar',
                'name' => $name,
                'url_key' => str($name)->slug().'-ar-'.fake()->unique()->numberBetween(1, 999999),
            ],
        ]);

        return $product;
    }

    private function payload(Product $product, array $overrides = []): array
    {
        $english = $product->translations()->where('locale', 'en')->firstOrFail();
        $arabic = $product->translations()->where('locale', 'ar')->firstOrFail();

        return array_merge([
            'sku' => $product->sku,
            'product_number' => $product->product_number,
            'product_name_en' => $english->name,
            'product_name_ar' => $arabic->name,
            'url_key_en' => $english->url_key,
            'url_key_ar' => $arabic->url_key,
            'price' => $product->price,
            'special_price' => null,
            'special_price_from' => null,
            'special_price_to' => null,
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
