<?php

namespace Tests\Feature\Concurrency;

use App\Enums\AccountType;
use App\Enums\CartItemType;
use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\ProductType;
use App\Enums\ShippingMethodType;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\DocumentSequence;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\ConcurrentProcessRunner;
use Tests\TestCase;

class MySqlConcurrencyTest extends TestCase
{
    private string $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->environment('testing')) {
            throw new RuntimeException('The concurrency suite may run only with APP_ENV=testing.');
        }

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('True row-lock concurrency is verified only against MySQL.');
        }

        $database = (string) DB::connection()->getDatabaseName();

        if (! preg_match('/test|testing/i', $database)) {
            throw new RuntimeException("Refusing to run concurrency tests against database [{$database}].");
        }

        foreach (['users', 'products', 'orders', 'carts', 'document_sequences'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("The migrated MySQL test table [{$table}] is required.");
            }
        }

        $this->scenario = 'cx'.strtolower(bin2hex(random_bytes(6)));
    }

    protected function tearDown(): void
    {
        if (isset($this->scenario) && DB::getDriverName() === 'mysql') {
            $this->cleanupScenario();
        }

        parent::tearDown();
    }

    public function test_document_sequence_contention_is_atomic_and_sequential(): void
    {
        $documentType = $this->scenario.'_document';
        DocumentSequence::query()->create([
            'document_type' => $documentType,
            'last_number' => 0,
        ]);

        $results = $this->runner()->run([
            ['action' => 'document_number', 'payload' => ['document_type' => $documentType]],
            ['action' => 'document_number', 'payload' => ['document_type' => $documentType]],
        ]);

        $this->assertEqualsCanonicalizing([1, 2], array_column($results, 'number'));
        $this->assertTrue(collect($results)->every(fn (array $result) => $result['successful'] === true));
        $this->assertSame(2, (int) DocumentSequence::query()
            ->where('document_type', $documentType)->value('last_number'));
    }

    public function test_same_cart_cannot_create_duplicate_orders(): void
    {
        [$customer, $shipping, $payment] = $this->checkoutDependencies();
        $product = $this->product('same-cart', '10.0000');
        $cart = $this->cart($customer, [[$product, '1.0000']]);
        $payload = $this->checkoutPayload($cart, $customer, $shipping, $payment);

        $results = $this->runner()->run([
            ['action' => 'checkout', 'payload' => $payload],
            ['action' => 'checkout', 'payload' => $payload],
        ]);

        $this->assertSame(1, collect($results)->where('successful', true)->count());
        $this->assertSame(1, Order::query()->where('user_id', $customer->id)->count());
        $this->assertSame(1, DB::table('order_payments')
            ->whereIn('order_id', Order::query()->where('user_id', $customer->id)->select('id'))->count());
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
        $this->assertTrue(collect($results)->where('successful', false)->contains(
            fn (array $result) => array_intersect($result['failure_codes'] ?? [], ['empty_cart', 'cart_changed']) !== []
        ));
    }

    public function test_concurrent_processing_cannot_oversell_inventory(): void
    {
        $product = $this->product('stock-contention', '10.0000');
        $first = $this->pendingOrder([[$product, '6.0000']]);
        $second = $this->pendingOrder([[$product, '6.0000']]);

        $results = $this->runner()->run([
            ['action' => 'process_order', 'payload' => ['order_id' => $first->id]],
            ['action' => 'process_order', 'payload' => ['order_id' => $second->id]],
        ]);

        $this->assertSame(1, collect($results)->where('successful', true)->count());
        $this->assertSame(1, Order::query()->whereIn('id', [$first->id, $second->id])
            ->where('status', OrderStatus::Processing->value)->count());
        $this->assertSame('4.0000', $product->inventory()->value('quantity'));
        $this->assertSame(1, DB::table('inventory_movements')
            ->where('product_id', $product->id)->where('type', 'sale')->count());
        $this->assertSame(1, Order::query()->whereIn('id', [$first->id, $second->id])
            ->where('status', OrderStatus::Pending->value)->count());
    }

    public function test_final_coupon_usage_limit_is_serialized_across_checkouts(): void
    {
        [$firstCustomer, $shipping, $payment] = $this->checkoutDependencies('coupon-a');
        $secondCustomer = $this->customer('coupon-b');
        $product = $this->product('coupon-product', '20.0000');
        $coupon = Coupon::query()->create([
            'code' => strtoupper($this->scenario).'COUPON',
            'name' => $this->scenario.' coupon',
            'type' => CouponType::Fixed,
            'value' => '1.0000',
            'is_active' => true,
            'usage_limit' => 1,
        ]);
        $firstCart = $this->cart($firstCustomer, [[$product, '1.0000']], $coupon);
        $secondCart = $this->cart($secondCustomer, [[$product, '1.0000']], $coupon);

        $results = $this->runner()->run([
            ['action' => 'checkout', 'payload' => $this->checkoutPayload($firstCart, $firstCustomer, $shipping, $payment)],
            ['action' => 'checkout', 'payload' => $this->checkoutPayload($secondCart, $secondCustomer, $shipping, $payment)],
        ]);

        $this->assertSame(1, DB::table('coupon_usages')->where('coupon_id', $coupon->id)->count());
        $this->assertSame(1, collect($results)->where('successful', true)->count());
        $this->assertSame(1, collect($results)->where('successful', false)->count());
        $this->assertSame(1, DB::table('orders')->whereIn('user_id', [$firstCustomer->id, $secondCustomer->id])->count());
        $this->assertSame(1, DB::table('order_payments')->whereIn(
            'order_id',
            DB::table('orders')->whereIn('user_id', [$firstCustomer->id, $secondCustomer->id])->select('id')
        )->count());
    }

    public function test_multi_inventory_processing_uses_deterministic_lock_order(): void
    {
        $firstProduct = $this->product('lock-a', '10.0000');
        $secondProduct = $this->product('lock-b', '10.0000');
        $first = $this->pendingOrder([[$firstProduct, '2.0000'], [$secondProduct, '2.0000']]);
        $second = $this->pendingOrder([[$secondProduct, '2.0000'], [$firstProduct, '2.0000']]);

        $results = $this->runner()->run([
            ['action' => 'process_order', 'payload' => ['order_id' => $first->id]],
            ['action' => 'process_order', 'payload' => ['order_id' => $second->id]],
        ]);

        $this->assertTrue(collect($results)->every(fn (array $result) => $result['successful'] === true));
        $this->assertSame('6.0000', $firstProduct->inventory()->value('quantity'));
        $this->assertSame('6.0000', $secondProduct->inventory()->value('quantity'));
        $this->assertSame(4, DB::table('inventory_movements')
            ->whereIn('product_id', [$firstProduct->id, $secondProduct->id])
            ->where('type', 'sale')->count());
    }

    private function runner(): ConcurrentProcessRunner
    {
        return new ConcurrentProcessRunner(45);
    }

    private function customer(string $suffix = 'customer'): User
    {
        return User::factory()->create([
            'name' => $this->scenario.' '.$suffix,
            'email' => "{$this->scenario}-{$suffix}@example.test",
            'account_type' => AccountType::Customer,
            'has_account' => true,
            'is_active' => true,
        ]);
    }

    /** @return array{User, ShippingMethod, PaymentMethod} */
    private function checkoutDependencies(string $suffix = 'checkout'): array
    {
        $customer = $this->customer($suffix);
        $shipping = ShippingMethod::query()->create([
            'code' => $this->scenario.'-'.$suffix.'-shipping',
            'name' => 'Concurrency Shipping',
            'type' => ShippingMethodType::Delivery,
            'amount' => '0.0000',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $payment = PaymentMethod::query()->create([
            'code' => $this->scenario.'-'.$suffix.'-payment',
            'name' => 'Concurrency Payment',
            'type' => PaymentMethodType::Offline,
            'is_active' => true,
            'requires_payment_before_processing' => false,
            'sort_order' => 0,
        ]);

        return [$customer, $shipping, $payment];
    }

    private function product(string $suffix, string $quantity): Product
    {
        $product = Product::factory()->create([
            'sku' => strtoupper($this->scenario.'-'.$suffix),
            'type' => ProductType::Simple,
            'price' => '10.0000',
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $product->translations()->create([
            'locale' => 'en',
            'name' => 'Concurrency '.$suffix,
            'url_key' => $this->scenario.'-'.$suffix,
        ]);
        $product->inventory()->create([
            'quantity' => $quantity,
            'average_cost' => '3.0000',
            'low_stock_alert' => '1.0000',
        ]);

        return $product;
    }

    /** @param list<array{0: Product, 1: string}> $lines */
    private function cart(User $customer, array $lines, ?Coupon $coupon = null): Cart
    {
        $timestamp = now();
        $cart = Cart::query()->create([
            'user_id' => $customer->id,
            'coupon_id' => $coupon?->id,
            'last_activity_at' => $timestamp,
            'expires_at' => $timestamp->copy()->addDays(30),
        ]);

        foreach ($lines as [$product, $quantity]) {
            $cart->items()->create([
                'product_id' => $product->id,
                'product_type' => CartItemType::Simple,
                'configuration_hash' => hash('sha256', 'simple-'.$product->id),
                'quantity' => $quantity,
            ]);
        }

        return $cart;
    }

    /** @return array<string, mixed> */
    private function checkoutPayload(
        Cart $cart,
        User $customer,
        ShippingMethod $shipping,
        PaymentMethod $payment
    ): array {
        $address = [
            'first_name' => 'Concurrent',
            'last_name' => 'Customer',
            'company' => null,
            'email' => $customer->email,
            'phone' => '70123456',
            'address_line_1' => 'Concurrency Street',
            'address_line_2' => null,
            'city' => 'Beirut',
            'state' => null,
            'postal_code' => null,
            'country_code' => 'LB',
        ];

        return [
            'cart_id' => $cart->id,
            'customer_id' => $customer->id,
            'checkout_data' => [
                'shipping_method' => $shipping->code,
                'payment_method' => $payment->code,
                'customer' => [
                    'first_name' => 'Concurrent',
                    'last_name' => 'Customer',
                    'phone' => '70123456',
                    'email' => $customer->email,
                ],
                'address_source' => 'manual',
                'manual_address' => $address,
            ],
        ];
    }

    /** @param list<array{0: Product, 1: string}> $lines */
    private function pendingOrder(array $lines): Order
    {
        $order = Order::query()->create([
            'order_number' => strtoupper($this->scenario).'-'.bin2hex(random_bytes(4)),
            'customer_email' => $this->scenario.'@example.test',
            'customer_first_name' => 'Concurrent',
            'customer_last_name' => 'Order',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => OrderStatus::Pending->value,
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'concurrency',
            'requires_payment_before_processing' => false,
            'subtotal' => '10.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '10.0000',
            'placed_at' => now(),
        ]);

        foreach ($lines as [$product, $quantity]) {
            $order->items()->create([
                'product_id' => $product->id,
                'product_type' => 'simple',
                'sku' => $product->sku,
                'name' => $product->sku,
                'quantity' => $quantity,
                'original_unit_price' => '10.0000',
                'unit_price' => '10.0000',
                'tax_amount' => '0.0000',
                'row_subtotal' => '10.0000',
                'row_total' => '10.0000',
                'unit_cost' => null,
                'is_inventory_item' => true,
            ]);
        }

        return $order;
    }

    private function cleanupScenario(): void
    {
        $userIds = User::query()->where('email', 'like', $this->scenario.'-%@example.test')->pluck('id');
        $orderIds = Order::query()
            ->whereIn('user_id', $userIds)
            ->orWhere('order_number', 'like', strtoupper($this->scenario).'-%')
            ->pluck('id');
        $productIds = Product::query()->where('sku', 'like', strtoupper($this->scenario).'-%')->pluck('id');
        $couponIds = Coupon::query()->where('code', 'like', strtoupper($this->scenario).'%')->pluck('id');

        DB::transaction(function () use ($userIds, $orderIds, $productIds, $couponIds): void {
            DB::table('database_notifications')->whereIn('entity_id', $orderIds)->where('entity_type', 'order')->delete();
            DB::table('coupon_usage_releases')->whereIn(
                'coupon_usage_id',
                DB::table('coupon_usages')->whereIn('order_id', $orderIds)->select('id')
            )->delete();
            DB::table('coupon_usages')->whereIn('order_id', $orderIds)->delete();
            DB::table('inventory_movements')->whereIn('reference_id', $orderIds)
                ->where('reference_type', Order::class)->delete();
            Order::query()->whereIn('id', $orderIds)->delete();
            Cart::query()->whereIn('user_id', $userIds)->delete();
            Coupon::query()->whereIn('id', $couponIds)->delete();
            Product::query()->whereIn('id', $productIds)->delete();
            ShippingMethod::query()->where('code', 'like', $this->scenario.'-%')->delete();
            PaymentMethod::query()->where('code', 'like', $this->scenario.'-%')->delete();
            User::query()->whereIn('id', $userIds)->delete();
            DocumentSequence::query()->where('document_type', 'like', $this->scenario.'%')->delete();
        });
    }
}
