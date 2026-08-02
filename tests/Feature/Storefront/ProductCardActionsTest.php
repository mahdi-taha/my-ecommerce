<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCardActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_in_stock_card_has_fallback_cart_form_and_stable_count_hooks(): void
    {
        $product = $this->product(ProductType::Simple, 3);

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('data-product-card-cart-form', false)
            ->assertSee('data-storefront-cart-form', false)
            ->assertSee('data-cart-url="'.route('shop.cart.index').'"', false)
            ->assertSee('data-view-cart-label="'.__('shop.cart.view_cart').'"', false)
            ->assertSee('data-continue-shopping-label="'.__('shop.cart.continue_shopping').'"', false)
            ->assertSee('name="quantity" value="1"', false)
            ->assertSee('data-storefront-cart-link', false)
            ->assertSee('data-storefront-action-status', false);
    }

    public function test_configurable_card_links_to_details_instead_of_posting_to_cart(): void
    {
        $product = $this->product(ProductType::Configurable, 0);

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee(route('shop.products.show', 'product-'.$product->id), false)
            ->assertSee(__('shop.product.choose_options'));
    }

    public function test_out_of_stock_simple_card_is_disabled(): void
    {
        $this->product(ProductType::Simple, 0);

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertDontSee('data-product-card-cart-form', false)
            ->assertSee(__('shop.product.out_of_stock'));
    }

    public function test_zero_price_cards_are_visible_but_commerce_is_unavailable(): void
    {
        $product = $this->product(ProductType::Simple, 5);
        $product->update(['price' => 0]);

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('Product '.$product->id)
            ->assertDontSee('data-product-card-cart-form', false)
            ->assertSee(__('shop.product.unavailable'));
    }

    public function test_product_details_cart_form_uses_the_shared_ajax_contract(): void
    {
        $product = $this->product(ProductType::Simple, 3);

        $this->get(route('shop.products.show', 'product-'.$product->id))
            ->assertOk()
            ->assertSee('action="'.route('shop.cart.items.store').'"', false)
            ->assertSee('data-storefront-cart-form', false)
            ->assertSee('data-cart-url="'.route('shop.cart.index').'"', false)
            ->assertSee('data-view-cart-label="'.__('shop.cart.view_cart').'"', false)
            ->assertSee('data-continue-shopping-label="'.__('shop.cart.continue_shopping').'"', false);
    }

    public function test_cart_success_uses_confirmation_and_wishlist_success_remains_a_toast(): void
    {
        $script = file_get_contents(resource_path('js/shop/product-card-actions.js'));

        $this->assertStringContainsString('await confirmCartAddition(form, payload.message);', $script);
        $this->assertStringContainsString('confirmButtonText: form.dataset.viewCartLabel', $script);
        $this->assertStringContainsString('cancelButtonText: form.dataset.continueShoppingLabel', $script);
        $this->assertStringContainsString('window.location.assign(form.dataset.cartUrl);', $script);
        $this->assertStringContainsString("document.querySelector('[data-storefront-action-status]')", $script);
        $this->assertStringContainsString('announce(payload.message);', $script);
    }

    private function product(ProductType $type, int $stock): Product
    {
        $product = Product::factory()->create([
            'type' => $type->value,
            'status' => true,
            'is_visible_individually' => true,
            'price' => 100,
        ]);
        $product->translations()->create([
            'locale' => app()->getLocale(),
            'name' => 'Product '.$product->id,
            'url_key' => 'product-'.$product->id,
        ]);
        $product->inventory()->create([
            'quantity' => $stock,
            'average_cost' => 10,
            'low_stock_alert' => 1,
        ]);

        if ($type === ProductType::Configurable) {
            $attribute = Attribute::factory()->create([
                'type' => 'select',
                'is_configurable' => true,
                'is_active' => true,
            ]);
            $option = $attribute->options()->create(['code' => 'default', 'sort_order' => 1]);
            $product->superAttributes()->create([
                'attribute_id' => $attribute->id,
            ])->options()->sync([$option->id]);
            $variant = Product::factory()->create([
                'type' => ProductType::Simple->value,
                'configurable_id' => $product->id,
                'status' => true,
                'is_visible_individually' => false,
                'price' => 100,
            ]);
            $variant->attributeValues()->create([
                'attribute_id' => $attribute->id,
                'attribute_option_id' => $option->id,
            ]);
        }

        return $product->fresh(['inventory']);
    }
}
