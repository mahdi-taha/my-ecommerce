<?php

namespace Tests\Feature\Storefront;

use App\Enums\CartItemType;
use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\GuestCartTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZeroPriceCommerceProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_predicate_respects_active_special_price_window(): void
    {
        $product = Product::factory()->create([
            'price' => 100,
            'special_price' => 0,
            'special_price_from' => now()->subMinute(),
            'special_price_to' => now()->addMinute(),
        ]);

        $this->assertFalse($product->hasPositiveEffectivePrice());

        $product->update(['special_price_from' => now()->addDay()]);
        $this->assertTrue($product->fresh()->hasPositiveEffectivePrice());

        $product->update([
            'special_price_from' => now()->subDays(2),
            'special_price_to' => now()->subDay(),
        ]);
        $this->assertTrue($product->fresh()->hasPositiveEffectivePrice());
    }

    public function test_guest_cart_merge_removes_a_product_that_changed_to_zero_price(): void
    {
        $tokens = app(GuestCartTokenService::class);
        $rawToken = $tokens->generate();
        $customer = User::factory()->customer()->create();
        $product = $this->product();
        $guestCart = Cart::query()->create([
            'guest_token_hash' => $tokens->hash($rawToken),
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $guestCart->items()->create([
            'product_id' => $product->id,
            'product_type' => CartItemType::Simple,
            'configuration_hash' => hash('sha256', 'zero-price-'.$product->id),
            'quantity' => 1,
        ]);
        $product->update(['price' => 0]);

        $warnings = app(CartService::class)->mergeGuestCart($customer, $rawToken);

        $this->assertContains(__('shop.cart.warnings.removed_unavailable'), $warnings);
        $this->assertModelMissing($guestCart);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    private function product(): Product
    {
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
            'price' => 100,
        ]);
        $product->translations()->create([
            'locale' => 'en',
            'name' => 'Commerce Product',
            'url_key' => 'commerce-product-'.$product->id,
        ]);
        $product->inventory()->create([
            'quantity' => 5,
            'average_cost' => 10,
            'low_stock_alert' => 1,
        ]);

        return $product;
    }
}
