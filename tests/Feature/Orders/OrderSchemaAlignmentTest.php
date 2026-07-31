<?php

namespace Tests\Feature\Orders;

use App\Enums\CartItemType;
use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\CheckoutOrderPlacementService;
use App\Services\GuestCartTokenService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderSchemaAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_schema_allows_null_customer_email_and_has_no_admin_notes(): void
    {
        $this->assertTrue(Schema::hasColumn('orders', 'customer_email'));
        $this->assertFalse(Schema::hasColumn('orders', 'admin_notes'));

        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-SCHEMA-NULL-EMAIL',
            'customer_email' => null,
            'customer_first_name' => 'Guest',
            'customer_last_name' => 'Customer',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'subtotal' => '1.0000',
            'grand_total' => '1.0000',
            'placed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'customer_email' => null,
        ]);
    }

    public function test_guest_checkout_persists_null_email(): void
    {
        [$cart, $shipping, $payment, $token] = $this->checkoutScenario();

        $result = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->checkoutData($shipping, $payment),
            null,
            $token
        );

        $this->assertTrue($result->successful);
        $this->assertNull($result->order->customer_email);
    }

    public function test_authenticated_checkout_snapshots_customer_email(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'schema-customer@example.test',
        ]);
        [$cart, $shipping, $payment] = $this->checkoutScenario($customer);

        $result = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->checkoutData($shipping, $payment),
            $customer
        );

        $this->assertTrue($result->successful);
        $this->assertSame($customer->email, $result->order->customer_email);
    }

    public function test_demo_database_seeder_succeeds_without_admin_notes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertFalse(Schema::hasColumn('orders', 'admin_notes'));
        $this->assertDatabaseCount('orders', 11);
    }

    /** @return array{Cart, ShippingMethod, PaymentMethod, ?string} */
    private function checkoutScenario(?User $customer = null): array
    {
        foreach ([
            ['currency', 'default_currency', 'USD', 'string'],
            ['tax', 'tax_mode', 'b2b', 'string'],
            ['cart', 'lifetime_days', '30', 'integer'],
            ['checkout', 'allow_guest_checkout', '1', 'boolean'],
        ] as [$group, $key, $value, $type]) {
            Setting::query()->updateOrCreate(compact('group', 'key'), compact('value', 'type'));
            cache()->forget("setting.{$group}.{$key}");
        }

        $token = $customer ? null : bin2hex(random_bytes(32));
        $cart = Cart::query()->create([
            'user_id' => $customer?->id,
            'guest_token_hash' => $token ? app(GuestCartTokenService::class)->hash($token) : null,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $product = Product::factory()->create([
            'type' => ProductType::Simple,
            'price' => '10.0000',
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $product->translations()->create([
            'locale' => 'en',
            'name' => 'Schema Product',
            'url_key' => 'schema-product-'.$product->id,
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
        $shipping = ShippingMethod::factory()->create([
            'amount' => '0.0000',
            'is_active' => true,
        ]);
        $payment = PaymentMethod::factory()->create(['is_active' => true]);

        return [$cart, $shipping, $payment, $token];
    }

    /** @return array<string, mixed> */
    private function checkoutData(ShippingMethod $shipping, PaymentMethod $payment): array
    {
        return [
            'shipping_method' => $shipping->code,
            'payment_method' => $payment->code,
            'customer' => [
                'first_name' => 'Schema',
                'last_name' => 'Customer',
                'phone' => '70123456',
            ],
            'address_source' => 'manual',
            'manual_address' => [
                'first_name' => 'Schema',
                'last_name' => 'Customer',
                'company' => null,
                'email' => null,
                'phone' => '70123456',
                'address_line_1' => 'Schema Street',
                'address_line_2' => null,
                'city' => 'Beirut',
                'state' => null,
                'postal_code' => null,
                'country_code' => 'LB',
            ],
        ];
    }
}
