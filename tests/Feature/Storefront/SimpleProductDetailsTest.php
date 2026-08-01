<?php

namespace Tests\Feature\Storefront;

use App\Enums\AttributeType;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleProductDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_simple_product_loads_by_current_locale_url_key(): void
    {
        $product = $this->product();

        $product->inventory()->create([
            'quantity' => 5,
            'average_cost' => 10,
            'low_stock_alert' => 1,
        ]);

        $this->get(route('shop.products.show', 'camera-en'))
            ->assertOk()
            ->assertSee('Localized Camera')
            ->assertSee('A localized short description.')
            ->assertSee('A localized long description.')
            ->assertSee('Available quantity: 5')
            ->assertSee('min="1"', false)
            ->assertSee('max="5.0000"', false)
            ->assertDontSee('Out of Stock');
    }

    public function test_out_of_stock_product_disables_quantity_controls_and_add_to_cart(): void
    {
        $this->product();

        $this->get(route('shop.products.show', 'camera-en'))
            ->assertOk()
            ->assertSee('Out of Stock')
            ->assertDontSee('Available quantity:')
            ->assertSee('value="0"', false)
            ->assertSee('type="submit"', false)
            ->assertSee('disabled', false);
    }

    public function test_zero_effective_price_is_visible_but_purchase_controls_are_unavailable(): void
    {
        $product = $this->product(['price' => 100, 'special_price' => 0]);
        $product->inventory()->create(['quantity' => 5, 'average_cost' => 10, 'low_stock_alert' => 1]);

        $this->get(route('shop.products.show', 'camera-en'))
            ->assertOk()
            ->assertSee(__('shop.product.unavailable'))
            ->assertSee('name="quantity"', false)
            ->assertSee('disabled', false);
    }

    public function test_product_resolution_is_locale_specific_without_fallback(): void
    {
        $this->product();

        app()->setLocale('en');
        $this->get(route('shop.products.show', 'camera-ar'))->assertNotFound();

        $this->withSession(['storefront_locale' => 'ar'])
            ->get(route('shop.products.show', 'camera-ar'))
            ->assertOk()
            ->assertSee('كاميرا محلية');
    }

    public function test_invalid_and_ineligible_products_return_not_found(): void
    {
        $this->get(route('shop.products.show', 'missing-product'))->assertNotFound();

        $inactive = $this->product(['status' => false], 'inactive');
        $hidden = $this->product(['is_visible_individually' => false], 'hidden');
        $parent = Product::factory()->create(['type' => ProductType::Configurable->value]);
        $variant = $this->product([
            'configurable_id' => $parent->id,
        ], 'variant');

        foreach ([$inactive, $hidden, $variant] as $product) {
            $urlKey = $product->translations()->where('locale', 'en')->value('url_key');

            $this->get(route('shop.products.show', $urlKey))->assertNotFound();
        }
    }

    public function test_gallery_places_base_image_before_remaining_sorted_images(): void
    {
        $product = $this->product();
        $product->images()->createMany([
            ['path' => 'products/third.jpg', 'is_base' => false, 'sort_order' => 20],
            ['path' => 'products/base.jpg', 'is_base' => true, 'sort_order' => 30],
            ['path' => 'products/second.jpg', 'is_base' => false, 'sort_order' => 10],
        ]);

        $this->get(route('shop.products.show', 'camera-en'))
            ->assertOk()
            ->assertSeeInOrder([
                '/storage/products/base.jpg',
                '/storage/products/second.jpg',
                '/storage/products/third.jpg',
            ], false);
    }

    public function test_breadcrumb_uses_first_active_category_and_its_parent_hierarchy(): void
    {
        $product = $this->product();
        $root = $this->category('Electronics', 5);
        $selected = $this->category('Cameras', 1, $root);
        $later = $this->category('Later Category', 2);

        $product->categories()->attach([$later->id, $selected->id]);

        $response = $this->get(route('shop.products.show', 'camera-en'));

        $response
            ->assertOk()
            ->assertSeeInOrder([
                'Home',
                'Electronics',
                'Cameras',
                'Localized Camera',
            ]);
        $this->assertSame(
            ['Electronics', 'Cameras'],
            $response->viewData('breadcrumbCategories')
                ->map(fn (Category $category) => $category->translations->first()->name)
                ->all()
        );
    }

    public function test_non_empty_front_visible_attributes_render_with_localized_labels(): void
    {
        $product = $this->product();

        $brand = Attribute::factory()->create([
            'type' => AttributeType::Text->value,
            'sort_order' => 1,
        ]);
        $brand->translations()->create([
            'locale' => 'en',
            'admin_name' => 'Brand',
        ]);
        $product->attributeValues()->create([
            'attribute_id' => $brand->id,
            'value' => 'Canon',
        ]);

        $color = Attribute::factory()->create([
            'type' => AttributeType::Select->value,
            'sort_order' => 2,
        ]);
        $color->translations()->create([
            'locale' => 'en',
            'admin_name' => 'Color',
        ]);
        $black = AttributeOption::factory()->create([
            'attribute_id' => $color->id,
            'code' => 'black',
        ]);
        $black->translations()->create([
            'locale' => 'en',
            'label' => 'Black',
        ]);
        $product->attributeValues()->create([
            'attribute_id' => $color->id,
            'attribute_option_id' => $black->id,
        ]);

        $empty = Attribute::factory()->create(['sort_order' => 3]);
        $empty->translations()->create([
            'locale' => 'en',
            'admin_name' => 'Empty Attribute',
        ]);
        $product->attributeValues()->create([
            'attribute_id' => $empty->id,
            'value' => '',
        ]);

        $hidden = Attribute::factory()->create([
            'is_visible_on_front' => false,
            'sort_order' => 4,
        ]);
        $hidden->translations()->create([
            'locale' => 'en',
            'admin_name' => 'Internal Attribute',
        ]);
        $product->attributeValues()->create([
            'attribute_id' => $hidden->id,
            'value' => 'Secret',
        ]);

        $this->get(route('shop.products.show', 'camera-en'))
            ->assertOk()
            ->assertSeeInOrder(['Brand', 'Canon', 'Color', 'Black'])
            ->assertDontSee('Empty Attribute')
            ->assertDontSee('Internal Attribute')
            ->assertDontSee('Secret');
    }

    private function product(array $state = [], string $key = 'camera'): Product
    {
        $product = Product::factory()->create(array_merge([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
            'price' => 335,
        ], $state));

        $product->translations()->createMany([
            [
                'locale' => 'en',
                'name' => 'Localized Camera',
                'url_key' => "{$key}-en",
                'short_description' => 'A localized short description.',
                'description' => 'A localized long description.',
            ],
            [
                'locale' => 'ar',
                'name' => 'كاميرا محلية',
                'url_key' => "{$key}-ar",
                'short_description' => 'وصف قصير محلي.',
                'description' => 'وصف طويل محلي.',
            ],
        ]);

        return $product;
    }

    private function category(string $name, int $position, ?Category $parent = null): Category
    {
        $category = Category::factory()->create([
            'parent_id' => $parent?->id,
            'position' => $position,
            'level' => $parent ? $parent->level + 1 : 0,
            'status' => true,
        ]);
        $category->translations()->create([
            'locale' => 'en',
            'name' => $name,
            'slug' => strtolower($name),
        ]);

        return $category;
    }
}
