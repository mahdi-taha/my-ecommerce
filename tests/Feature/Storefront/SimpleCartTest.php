<?php

namespace Tests\Feature\Storefront;

use App\Enums\AccountType;
use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Services\GuestCartTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SimpleCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_ajax_add_returns_authoritative_count_and_preserves_guest_cookie(): void
    {
        $product = $this->product(stock: 5);

        $this->postJson(route('shop.cart.items.store'), [
            'product_type' => ProductType::Simple->value,
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart_count', 1)
            ->assertCookie(GuestCartTokenService::COOKIE_NAME);
    }

    public function test_guest_can_add_an_eligible_simple_product_without_changing_inventory(): void
    {
        $product = $this->product(stock: 5);

        $response = $this->post(route('shop.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 0.01,
        ]);

        $response->assertRedirect(route('shop.cart.index'))
            ->assertCookie(GuestCartTokenService::COOKIE_NAME);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertSame('5.0000', $product->inventory->fresh()->quantity);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_adding_the_same_product_merges_and_validates_combined_quantity(): void
    {
        $customer = $this->customer();
        $product = $this->product(stock: 5);
        $this->actingAs($customer, 'customer');

        $this->post(route('shop.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect();
        $this->post(route('shop.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertRedirect();

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame('5.0000', $customer->cart->items()->first()->quantity);

        $this->post(route('shop.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('quantity');

        $this->assertSame('5.0000', $customer->cart->items()->first()->quantity);
    }

    public function test_add_rejects_invalid_quantities_and_ineligible_products(): void
    {
        $eligible = $this->product(stock: 2);

        foreach ([0, -1, 1.5] as $quantity) {
            $this->post(route('shop.cart.items.store'), [
                'product_id' => $eligible->id,
                'quantity' => $quantity,
            ])->assertSessionHasErrors('quantity');
        }

        $this->post(route('shop.cart.items.store'), [
            'product_id' => $eligible->id,
            'quantity' => 3,
        ])->assertSessionHasErrors('quantity');

        $products = [
            $this->product(stock: 0),
            $this->product(stock: 2, state: ['status' => false]),
            $this->product(stock: 2, state: ['is_visible_individually' => false]),
            $this->product(stock: 2, state: ['type' => ProductType::Configurable->value]),
        ];
        $parent = Product::factory()->create(['type' => ProductType::Configurable->value]);
        $products[] = $this->product(stock: 2, state: ['configurable_id' => $parent->id]);

        foreach ($products as $product) {
            $this->post(route('shop.cart.items.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])->assertSessionHasErrors();
        }

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_zero_effective_price_cannot_be_added_or_updated(): void
    {
        $this->actingAs($this->customer(), 'customer');
        $product = $this->product(stock: 5);
        $this->post(route('shop.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect();
        $item = Cart::query()->firstOrFail()->items()->firstOrFail();

        $product->update(['special_price' => 0, 'special_price_from' => now()->subMinute()]);

        $this->get(route('shop.cart.index'))
            ->assertOk()
            ->assertSee('Cart Product '.$product->id);

        $this->post(route('shop.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('product_id');
        $this->patch(route('shop.cart.items.update', $item), ['quantity' => 2])
            ->assertSessionHasErrors('product_id');
        $this->assertSame('1.0000', $item->fresh()->quantity);
    }

    public function test_customer_can_update_remove_and_clear_owned_items(): void
    {
        $customer = $this->customer();
        $first = $this->product(stock: 5);
        $second = $this->product(stock: 5);
        $this->actingAs($customer, 'customer');

        foreach ([$first, $second] as $product) {
            $this->post(route('shop.cart.items.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);
        }

        $items = $customer->cart->items()->get();

        $this->patch(route('shop.cart.items.update', $items[0]->id), [
            'quantity' => 4,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('4.0000', $items[0]->fresh()->quantity);

        $this->delete(route('shop.cart.items.destroy', $items[0]->id))
            ->assertRedirect();
        $this->assertModelMissing($items[0]);

        $this->delete(route('shop.cart.clear'))->assertRedirect();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_customer_cannot_update_or_remove_another_carts_item(): void
    {
        $owner = $this->customer();
        $attacker = $this->customer();
        $product = $this->product(stock: 5);
        $this->actingAs($owner, 'customer');
        $this->post(route('shop.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $item = $owner->cart->items()->first();

        $this->actingAs($attacker, 'customer')
            ->patch(route('shop.cart.items.update', $item->id), ['quantity' => 2])
            ->assertSessionHasErrors('cart');
        $this->actingAs($attacker, 'customer')
            ->delete(route('shop.cart.items.destroy', $item->id))
            ->assertSessionHasErrors('cart');

        $this->assertSame('1.0000', $item->fresh()->quantity);
    }

    public function test_cart_uses_current_regular_special_and_tax_aware_prices(): void
    {
        $customer = $this->customer();
        $tax = Tax::create(['name' => 'Standard', 'rate' => 10, 'status' => true]);
        $regular = $this->product(stock: 5, state: [
            'price' => 100,
            'use_default_tax' => false,
            'tax_id' => $tax->id,
        ]);
        $special = $this->product(stock: 5, state: [
            'price' => 100,
            'special_price' => 80,
            'special_price_from' => now()->subDay(),
            'special_price_to' => now()->addDay(),
            'use_default_tax' => false,
            'tax_id' => $tax->id,
        ]);
        $this->actingAs($customer, 'customer');

        foreach ([$regular, $special] as $product) {
            $this->post(route('shop.cart.items.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);
        }

        $this->setting('tax', 'tax_mode', 'b2b');
        $this->get(route('shop.cart.index'))
            ->assertOk()
            ->assertSee('$ 180.00');

        $this->setting('tax', 'tax_mode', 'b2c');
        cache()->forget('setting.tax.tax_mode');
        $this->get(route('shop.cart.index'))
            ->assertOk()
            ->assertSee('$ 198.00');
    }

    public function test_cart_count_and_product_details_form_use_real_routes_without_touching_activity(): void
    {
        $customer = $this->customer();
        $product = $this->product(stock: 5, key: 'cart-product');
        $this->actingAs($customer, 'customer');
        $this->post(route('shop.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $activity = $customer->cart->last_activity_at;

        $this->get(route('shop.products.show', 'cart-product-en'))
            ->assertOk()
            ->assertSee(route('shop.cart.items.store'), false)
            ->assertSee('name="quantity"', false)
            ->assertSeeText('2');

        $this->assertTrue($activity->equalTo($customer->cart->fresh()->last_activity_at));
    }

    public function test_guest_cart_merges_into_customer_cart_caps_stock_and_expires_cookie(): void
    {
        $tokens = app(GuestCartTokenService::class);
        $rawToken = $tokens->generate();
        $customer = $this->customer('customer@example.test');
        $product = $this->product(stock: 5);
        $customerCart = Cart::create([
            'user_id' => $customer->id,
            'last_activity_at' => now()->subHour(),
            'expires_at' => now()->addDays(29),
        ]);
        $guestCart = Cart::create([
            'guest_token_hash' => $tokens->hash($rawToken),
            'last_activity_at' => now()->subHour(),
            'expires_at' => now()->addDays(29),
        ]);
        $hash = hash('sha256', json_encode([
            'type' => 'simple',
            'product_id' => $product->id,
        ], JSON_THROW_ON_ERROR));
        $customerCart->items()->create([
            'product_id' => $product->id,
            'product_type' => 'simple',
            'configuration_hash' => $hash,
            'quantity' => 3,
        ]);
        $guestCart->items()->create([
            'product_id' => $product->id,
            'product_type' => 'simple',
            'configuration_hash' => $hash,
            'quantity' => 4,
        ]);

        $response = $this->withCookie(GuestCartTokenService::COOKIE_NAME, $rawToken)
            ->post(route('customer.login.store'), [
                'email' => $customer->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('customer.account.edit'))
            ->assertSessionHas('warning')
            ->assertCookieExpired(GuestCartTokenService::COOKIE_NAME);
        $this->assertSame($customerCart->id, $customer->cart->fresh()->id);
        $this->assertSame('5.0000', $customer->cart->items()->first()->quantity);
        $this->assertModelMissing($guestCart);
        $this->assertDatabaseCount('cart_items', 1);
    }

    private function customer(?string $email = null): User
    {
        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'account_type' => AccountType::Customer,
            'has_account' => true,
            'is_active' => true,
        ]);
    }

    private function product(
        float $stock,
        array $state = [],
        string $key = 'product'
    ): Product {
        $product = Product::factory()->create(array_merge([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
            'price' => 100,
        ], $state));
        $product->translations()->create([
            'locale' => 'en',
            'name' => 'Cart Product '.$product->id,
            'url_key' => $key.'-'.$product->id.'-en',
        ]);
        $product->inventory()->create([
            'quantity' => $stock,
            'average_cost' => 10,
            'low_stock_alert' => 1,
        ]);

        if ($key === 'cart-product') {
            $product->translations()->where('locale', 'en')->update([
                'url_key' => 'cart-product-en',
            ]);
        }

        return $product->fresh(['inventory']);
    }

    private function setting(string $group, string $key, string $value): void
    {
        Setting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value, 'type' => 'select']
        );
        cache()->forget("setting.{$group}.{$key}");
    }
}
