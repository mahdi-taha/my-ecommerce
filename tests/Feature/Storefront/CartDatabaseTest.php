<?php

namespace Tests\Feature\Storefront;

use App\Enums\AccountType;
use App\Enums\CartItemType;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\GuestCartTokenService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CartDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_requires_exactly_one_owner(): void
    {
        $customer = $this->customer();
        $timestamp = now();

        foreach ([
            ['user_id' => null, 'guest_token_hash' => null],
            ['user_id' => $customer->id, 'guest_token_hash' => str_repeat('a', 64)],
        ] as $ownership) {
            try {
                DB::table('carts')->insert(array_merge($ownership, [
                    'last_activity_at' => $timestamp,
                    'expires_at' => $timestamp->copy()->addDays(30),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]));

                $this->fail('The database accepted an invalid Cart owner.');
            } catch (QueryException) {
                $this->assertDatabaseCount('carts', 0);
            }
        }
    }

    public function test_customer_and_guest_ownership_are_unique(): void
    {
        $customer = $this->customer();
        $this->cartForCustomer($customer);

        $this->expectException(QueryException::class);

        $this->cartForCustomer($customer);
    }

    public function test_cart_item_quantities_must_be_positive(): void
    {
        $cart = $this->cartForCustomer($this->customer());
        $product = Product::factory()->create();

        try {
            $cart->items()->create([
                'product_id' => $product->id,
                'product_type' => CartItemType::Simple->value,
                'configuration_hash' => str_repeat('b', 64),
                'quantity' => 0,
            ]);
            $this->fail('The database accepted a zero CartItem quantity.');
        } catch (QueryException) {
            $this->assertDatabaseCount('cart_items', 0);
        }

    }

    public function test_cart_deletion_cascades_to_items(): void
    {
        $cart = $this->cartForCustomer($this->customer());
        $product = Product::factory()->create();
        $cart->items()->create([
            'product_id' => $product->id,
            'product_type' => CartItemType::Simple->value,
            'configuration_hash' => str_repeat('c', 64),
            'quantity' => 1,
        ]);

        $cart->delete();

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_expired_carts_are_pruned_without_touching_active_carts(): void
    {
        $expired = Cart::create([
            'guest_token_hash' => str_repeat('e', 64),
            'last_activity_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
        ]);
        $active = Cart::create([
            'guest_token_hash' => str_repeat('f', 64),
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $deleted = app(CartService::class)->pruneExpired();

        $this->assertSame(1, $deleted);
        $this->assertModelMissing($expired);
        $this->assertModelExists($active);
    }

    public function test_guest_token_service_never_persists_the_raw_token(): void
    {
        $tokens = app(GuestCartTokenService::class);
        $token = $tokens->generate();
        $cart = Cart::create([
            'guest_token_hash' => $tokens->hash($token),
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $token);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $cart->guest_token_hash);
        $this->assertNotSame($token, $cart->guest_token_hash);
        $this->assertArrayNotHasKey('guest_token_hash', $cart->toArray());
    }

    private function customer(): User
    {
        return User::factory()->create([
            'account_type' => AccountType::Customer,
            'has_account' => true,
            'is_active' => true,
        ]);
    }

    private function cartForCustomer(User $customer): Cart
    {
        return Cart::create([
            'user_id' => $customer->id,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }
}
