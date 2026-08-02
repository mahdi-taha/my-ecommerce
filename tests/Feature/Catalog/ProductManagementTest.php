<?php

namespace Tests\Feature\Catalog;

use App\Models\Attribute;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_creation_does_not_create_inventory(): void
    {
        $product = app(ProductService::class)->create([
            'type' => 'simple',
            'sku' => 'SIMPLE-1',
            'product_number' => null,
            'product_name_en' => 'Simple',
            'product_name_ar' => 'بسيط',
        ]);

        $this->assertNull($product->inventory);
        $this->assertCount(2, $product->translations);
    }

    public function test_configurable_creation_stores_the_variant_base_price(): void
    {
        $product = app(ProductService::class)->create([
            'type' => 'configurable',
            'sku' => 'CONFIGURABLE-1',
            'product_number' => null,
            'product_name_en' => 'Configurable',
            'product_name_ar' => 'قابل للتخصيص',
            'price' => 12.5,
        ]);

        $this->assertSame('12.5000', $product->price);
        $this->assertNull($product->inventory);
    }

    public function test_product_price_rejects_more_than_four_decimal_places(): void
    {
        $this->actingAs(User::factory()->create(), 'admin')
            ->post(route('admin.products.store'), [
                'type' => 'configurable',
                'sku' => 'DECIMAL-SCALE',
                'product_name_en' => 'Decimal Scale',
                'product_name_ar' => 'Decimal Scale',
                'price' => '12.12345',
            ])
            ->assertSessionHasErrors('price');

        $this->assertDatabaseMissing('products', ['sku' => 'DECIMAL-SCALE']);
    }

    public function test_product_price_accepts_four_decimal_places(): void
    {
        $this->actingAs(User::factory()->create(), 'admin')
            ->post(route('admin.products.store'), [
                'type' => 'configurable',
                'sku' => 'DECIMAL-FOUR',
                'product_name_en' => 'Decimal Four',
                'product_name_ar' => 'Decimal Four',
                'price' => '12.1234',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'sku' => 'DECIMAL-FOUR',
            'price' => '12.1234',
        ]);
    }

    public function test_bundle_product_type_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(), 'admin')
            ->post(route('admin.products.store'), [
                'type' => 'bundle',
                'sku' => 'RETIRED-BUNDLE',
                'product_name_en' => 'Retired Bundle',
                'product_name_ar' => 'منتج مجمع',
            ])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseMissing('products', ['sku' => 'RETIRED-BUNDLE']);
    }

    public function test_variants_do_not_own_translations_or_categories(): void
    {
        $parent = Product::factory()->create(['type' => 'configurable']);
        $variant = Product::factory()->create([
            'configurable_id' => $parent->id,
            'is_visible_individually' => false,
        ]);

        $this->assertFalse($variant->translations()->exists());
        $this->assertFalse($variant->categories()->exists());
    }

    public function test_required_product_attribute_uses_its_admin_label_and_inline_error(): void
    {
        $product = Product::factory()->create();
        $product->translations()->createMany([
            ['locale' => 'en', 'name' => 'Product', 'url_key' => 'product-en'],
            ['locale' => 'ar', 'name' => 'منتج', 'url_key' => 'product-ar'],
        ]);
        $attribute = Attribute::factory()->create(['is_required' => true]);
        $attribute->translations()->create(['locale' => 'en', 'admin_name' => 'Color']);

        $response = $this
            ->actingAs(User::factory()->create(), 'admin')
            ->from(route('admin.products.edit', $product))
            ->followingRedirects()
            ->put(route('admin.products.update', $product), [
                'sku' => $product->sku,
                'product_number' => null,
                'product_name_en' => 'Product',
                'product_name_ar' => 'منتج',
                'url_key_en' => 'product-en',
                'url_key_ar' => 'product-ar',
                'price' => 10,
                'is_new' => false,
                'is_featured' => false,
                'is_visible_individually' => true,
                'status' => true,
                'attributes' => [],
            ]);

        $response
            ->assertOk()
            ->assertSee('Color is required.')
            ->assertDontSee('The attributes.'.$attribute->id.' field is required.')
            ->assertSee('is-invalid', false);
    }

    public function test_unused_product_can_be_deleted_with_owned_records_and_image_cleanup(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/deletable.jpg', 'image');
        $product = Product::factory()->create();
        $translation = $product->translations()->create([
            'locale' => 'en', 'name' => 'Deletable', 'url_key' => 'deletable',
        ]);
        $image = $product->images()->create([
            'path' => 'products/deletable.jpg', 'is_base' => true, 'sort_order' => 0,
        ]);

        $response = $this
            ->actingAs(User::factory()->create(), 'admin')
            ->deleteJson(route('admin.products.destroy', $product));

        $response->assertOk()->assertJson(['message' => 'Product deleted successfully.']);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_translations', ['id' => $translation->id]);
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing('products/deletable.jpg');
    }

    public function test_product_with_order_history_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();
        $this->order()->items()->create($this->orderItemData($product));

        $this->assertProductDeletionRejected($product);
    }

    public function test_product_with_inventory_movements_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();
        InventoryMovement::query()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OPENING,
            'quantity' => 1,
            'quantity_before' => 0,
            'quantity_after' => 1,
            'unit_cost' => 1,
            'total_cost' => 1,
        ]);

        $this->assertProductDeletionRejected($product);
    }

    public function test_configurable_parent_with_variants_cannot_be_deleted(): void
    {
        $product = Product::factory()->create(['type' => 'configurable']);
        Product::factory()->create(['configurable_id' => $product->id]);

        $this->assertProductDeletionRejected($product);
    }

    private function assertProductDeletionRejected(Product $product): void
    {
        try {
            app(ProductService::class)->delete($product);
            $this->fail('A referenced product was deleted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This product is in use and cannot be deleted.',
                $exception->errors()['product'][0]
            );
        }

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    private function order(): Order
    {
        return Order::query()->create([
            'order_number' => 'ORD-'.fake()->unique()->numerify('########'),
            'customer_email' => 'customer@example.com',
            'customer_first_name' => 'Test',
            'customer_last_name' => 'Customer',
            'locale' => 'en',
            'currency_code' => 'USD',
            'payment_method' => 'cash_on_delivery',
            'subtotal' => 10,
            'grand_total' => 10,
            'placed_at' => now(),
        ]);
    }

    private function orderItemData(Product $product): array
    {
        return [
            'product_id' => $product->id,
            'product_type' => 'simple',
            'sku' => $product->sku,
            'name' => 'Product',
            'quantity' => 1,
            'original_unit_price' => 10,
            'unit_price' => 10,
            'row_subtotal' => 10,
            'row_total' => 10,
            'is_inventory_item' => true,
        ];
    }
}
