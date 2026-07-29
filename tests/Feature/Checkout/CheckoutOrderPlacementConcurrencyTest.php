<?php

namespace Tests\Feature\Checkout;

use App\Enums\CartItemType;
use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\CheckoutOrderPlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CheckoutOrderPlacementConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sequential_duplicate_submission_creates_only_one_order(): void
    {
        [$cart, $customer, $data] = $this->scenario();
        $service = app(CheckoutOrderPlacementService::class);

        $first = $service->place($cart, $data, $customer);
        $second = $service->place($cart->fresh(), $data, $customer);

        $this->assertTrue($first->successful);
        $this->assertFalse($second->successful);
        $this->assertSame(['empty_cart'], $second->failureCodes());
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_payments', 1);
    }

    public function test_cart_mutation_after_initial_signature_is_rejected_as_stale(): void
    {
        [$cart, $customer, $data] = $this->scenario();
        $mutated = false;

        Event::listen('eloquent.retrieved: '.Cart::class, function (Cart $retrieved) use (&$mutated, $cart): void {
            if (! $mutated && $retrieved->is($cart)) {
                $mutated = true;
                DB::table('cart_items')->where('cart_id', $cart->id)->update(['quantity' => '2.0000']);
            }
        });

        $result = app(CheckoutOrderPlacementService::class)->place($cart, $data, $customer);

        $this->assertSame(['cart_changed'], $result->failureCodes());
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'quantity' => 2]);
    }

    public function test_sqlite_suite_does_not_claim_parallel_row_locking_guarantees(): void
    {
        $this->markTestSkipped('True concurrent lock blocking must be verified against MySQL; SQLite provides sequential regression coverage only.');
    }

    private function scenario(): array
    {
        foreach ([
            ['currency', 'default_currency', 'USD', 'string'],
            ['tax', 'tax_mode', 'b2b', 'string'],
            ['cart', 'lifetime_days', '30', 'integer'],
        ] as [$group, $key, $value, $type]) {
            Setting::query()->updateOrCreate(compact('group', 'key'), compact('value', 'type'));
            cache()->forget("setting.{$group}.{$key}");
        }

        $customer = User::factory()->customer()->create();
        $cart = Cart::create([
            'user_id' => $customer->id,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'price' => '15.0000',
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $product->translations()->create([
            'locale' => 'en',
            'name' => 'Concurrent Product',
            'url_key' => 'concurrent-product-'.$product->id,
        ]);
        $product->inventory()->create([
            'quantity' => '10.0000',
            'average_cost' => '3.0000',
            'low_stock_alert' => '1.0000',
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'product_type' => CartItemType::Simple,
            'configuration_hash' => hash('sha256', 'simple-'.$product->id),
            'quantity' => '1.0000',
        ]);
        $shipping = ShippingMethod::factory()->create(['amount' => '0.0000', 'is_active' => true]);
        $payment = PaymentMethod::factory()->create(['is_active' => true]);
        $address = [
            'first_name' => 'Concurrent', 'last_name' => 'Customer', 'company' => null,
            'email' => 'concurrent@example.com', 'phone' => '70123456',
            'address_line_1' => 'Test Street', 'address_line_2' => null,
            'city' => 'Beirut', 'state' => null, 'postal_code' => null, 'country_code' => 'LB',
        ];

        return [$cart, $customer, [
            'shipping_method' => $shipping->code,
            'payment_method' => $payment->code,
            'customer' => ['first_name' => 'Concurrent', 'last_name' => 'Customer', 'phone' => '70123456', 'email' => 'concurrent@example.com'],
            'billing_address' => $address,
            'shipping_address' => $address,
        ]];
    }
}
