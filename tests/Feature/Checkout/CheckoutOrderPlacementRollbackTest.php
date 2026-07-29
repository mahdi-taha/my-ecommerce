<?php

namespace Tests\Feature\Checkout;

use App\Enums\CartItemType;
use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\CheckoutOrderPlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CheckoutOrderPlacementRollbackTest extends TestCase
{
    use RefreshDatabase;

    public static function failureStages(): array
    {
        return [
            'after Order' => [Order::class, 1],
            'after addresses' => [OrderAddress::class, 2],
            'after items' => [OrderItem::class, 1],
            'after payment' => [OrderPayment::class, 1],
        ];
    }

    #[DataProvider('failureStages')]
    public function test_every_creation_stage_rolls_back_the_order_and_preserves_cart(
        string $model,
        int $throwOnOccurrence
    ): void {
        [$cart, $product, $customer, $shipping, $payment, $data] = $this->scenario();
        $occurrence = 0;

        Event::listen("eloquent.created: {$model}", function () use (&$occurrence, $throwOnOccurrence): void {
            $occurrence++;

            if ($occurrence === $throwOnOccurrence) {
                throw new RuntimeException('Injected Checkout persistence failure.');
            }
        });

        try {
            app(CheckoutOrderPlacementService::class)->place($cart, $data, $customer);
            $this->fail('The injected persistence failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected Checkout persistence failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_addresses', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_shipping', 0);
        $this->assertDatabaseCount('order_payments', 0);
        $this->assertDatabaseCount('order_status_history', 0);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('product_inventories', ['product_id' => $product->id, 'quantity' => 10]);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(0, (int) DB::table('document_sequences')->where('document_type', 'order')->value('last_number'));
        $this->assertSame(0, (int) DB::table('document_sequences')->where('document_type', 'payment')->value('last_number'));
    }

    private function scenario(): array
    {
        foreach ([
            ['currency', 'default_currency', 'USD', 'string'],
            ['tax', 'tax_mode', 'b2b', 'string'],
            ['cart', 'lifetime_days', '30', 'integer'],
        ] as [$group, $key, $value, $type]) {
            Setting::query()->updateOrCreate(
                compact('group', 'key'),
                compact('value', 'type')
            );
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
            'price' => '25.0000',
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $product->translations()->create([
            'locale' => 'en',
            'name' => 'Rollback Product',
            'url_key' => 'rollback-product-'.$product->id,
        ]);
        $product->inventory()->create([
            'quantity' => '10.0000',
            'average_cost' => '4.0000',
            'low_stock_alert' => '1.0000',
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'product_type' => CartItemType::Simple,
            'configuration_hash' => hash('sha256', 'simple-'.$product->id),
            'quantity' => '1.0000',
        ]);
        $shipping = ShippingMethod::factory()->create(['amount' => '2.0000', 'is_active' => true]);
        $payment = PaymentMethod::factory()->create(['is_active' => true]);
        $address = [
            'first_name' => 'Rollback', 'last_name' => 'Customer', 'company' => null,
            'email' => 'rollback@example.com', 'phone' => '70123456',
            'address_line_1' => 'Test Street', 'address_line_2' => null,
            'city' => 'Beirut', 'state' => null, 'postal_code' => null, 'country_code' => 'LB',
        ];
        $data = [
            'shipping_method' => $shipping->code,
            'payment_method' => $payment->code,
            'customer' => ['first_name' => 'Rollback', 'last_name' => 'Customer', 'phone' => '70123456', 'email' => 'rollback@example.com'],
            'billing_address' => $address,
            'shipping_address' => $address,
        ];

        return [$cart, $product, $customer, $shipping, $payment, $data];
    }
}
