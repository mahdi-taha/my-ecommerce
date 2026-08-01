<?php

namespace Tests\Feature\CustomerAccount;

use App\Enums\AccountType;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_non_customer_cannot_access_customer_orders(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer);

        $this->get(route('shop.account.orders.index'))->assertRedirect(route('customer.login'));
        $this->get(route('shop.account.orders.show', $order))->assertRedirect(route('customer.login'));

        $admin = User::factory()->create(['account_type' => AccountType::Admin]);
        $this->actingAs($admin, 'customer')
            ->get(route('shop.account.orders.index'))
            ->assertRedirect(route('customer.login'))
            ->assertSessionHas('error', __('shop.auth.account_inactive'));
        $this->assertGuest('customer');
    }

    public function test_customer_list_contains_only_owned_orders_and_excludes_guest_orders(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $owned = $this->order($customer, ['order_number' => 'ORD-2026-100001']);
        $otherOrder = $this->order($other, ['order_number' => 'ORD-2026-100002']);
        $guestOrder = $this->order(null, ['order_number' => 'ORD-2026-100003']);

        $this->actingAs($customer, 'customer')->get(route('shop.account.orders.index'))
            ->assertOk()
            ->assertSee($owned->order_number)
            ->assertDontSee($otherOrder->order_number)
            ->assertDontSee($guestOrder->order_number)
            ->assertSee(route('shop.account.orders.show', $owned), false);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->order(User::factory()->customer()->create());

        $this->actingAs($customer, 'customer')
            ->get(route('shop.account.orders.show', $order))
            ->assertNotFound();
    }

    public function test_orders_are_ordered_by_placed_at_then_id_and_paginated_by_ten(): void
    {
        $customer = User::factory()->customer()->create();

        for ($index = 1; $index <= 9; $index++) {
            $this->order($customer, [
                'order_number' => sprintf('ORD-2026-%06d', 200000 + $index),
                'placed_at' => now()->subDays($index),
            ]);
        }

        $sameTime = now()->addDay();
        $olderId = $this->order($customer, [
            'order_number' => 'ORD-2026-200010', 'placed_at' => $sameTime,
        ]);
        $newerId = $this->order($customer, [
            'order_number' => 'ORD-2026-200011', 'placed_at' => $sameTime,
        ]);

        $response = $this->actingAs($customer, 'customer')->get(route('shop.account.orders.index'));

        $response->assertOk()
            ->assertSeeInOrder([$newerId->order_number, $olderId->order_number])
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 10 && $orders->total() === 11);
        $this->get(route('shop.account.orders.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 1);
    }

    public function test_empty_order_history_displays_localized_empty_state(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'customer')->get(route('shop.account.orders.index'))
            ->assertOk()
            ->assertSee(__('shop.account.orders.no_orders'));
    }

    public function test_order_details_render_only_immutable_snapshots_and_newest_history_first(): void
    {
        $customer = User::factory()->customer()->create();
        $product = Product::factory()->create(['sku' => 'LIVE-SKU']);
        $shippingMethod = ShippingMethod::factory()->create(['name' => 'Live Shipping']);
        $paymentMethod = PaymentMethod::query()->where('code', 'cash_on_delivery')->firstOrFail();
        $order = $this->order($customer, [
            'order_number' => 'ORD-2026-300001',
            'subtotal' => '90.0000',
            'tax_total' => '9.0000',
            'shipping_total' => '6.0000',
            'grand_total' => '105.0000',
        ]);
        $this->snapshots($order, $product, $shippingMethod, $paymentMethod);
        $order->statusHistory()->create([
            'type' => 'order', 'from_status' => null, 'to_status' => 'pending',
            'comment' => 'First history', 'created_at' => now()->subHour(),
        ]);
        $order->statusHistory()->create([
            'type' => 'payment', 'from_status' => 'pending', 'to_status' => 'paid',
            'comment' => 'Newest history', 'created_at' => now(),
        ]);

        $product->update(['sku' => 'CHANGED-LIVE-SKU']);
        $shippingMethod->update(['name' => 'Changed Live Shipping']);
        $paymentMethod->update(['name' => 'Changed Live Payment']);

        $this->actingAs($customer, 'customer')->get(route('shop.account.orders.show', $order))
            ->assertOk()
            ->assertSee('Snapshot Product')
            ->assertSee('SNAPSHOT-SKU')
            ->assertSee('Color: Black')
            ->assertSee('Snapshot Shipping')
            ->assertSee('Snapshot Payment')
            ->assertSee('Billing Snapshot Street')
            ->assertSee('Shipping Snapshot Street')
            ->assertSee('$ 105.00')
            ->assertSeeInOrder(['Newest history', 'First history'])
            ->assertDontSee('CHANGED-LIVE-SKU')
            ->assertDontSee('Changed Live Shipping')
            ->assertDontSee('Changed Live Payment');
    }

    private function order(?User $customer, array $state = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-2026-'.fake()->unique()->numerify('######'),
            'user_id' => $customer?->id,
            'customer_email' => $customer?->email ?? 'guest@example.com',
            'customer_first_name' => 'Snapshot',
            'customer_last_name' => 'Customer',
            'customer_phone' => '70123456',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'requires_payment_before_processing' => false,
            'subtotal' => '10.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '10.0000',
            'placed_at' => now(),
        ], $state));
    }

    private function snapshots(
        Order $order,
        Product $product,
        ShippingMethod $shippingMethod,
        PaymentMethod $paymentMethod
    ): void {
        foreach ([
            ['type' => 'billing', 'address_line_1' => 'Billing Snapshot Street'],
            ['type' => 'shipping', 'address_line_1' => 'Shipping Snapshot Street'],
        ] as $address) {
            $order->addresses()->create(array_merge($address, [
                'first_name' => 'Snapshot', 'last_name' => 'Customer', 'company' => null,
                'email' => 'snapshot@example.com', 'phone' => '70123456',
                'address_line_2' => null, 'city' => 'Beirut', 'state' => null,
                'postal_code' => null, 'country_code' => 'LB',
            ]));
        }

        $order->shipping()->create([
            'shipping_method_id' => $shippingMethod->id,
            'shipping_method_code' => 'snapshot_shipping',
            'shipping_method_name' => 'Snapshot Shipping',
            'shipping_method_type' => 'delivery',
            'shipping_amount' => '6.0000',
        ]);
        $order->payment()->create([
            'payment_number' => 'PAY-2026-300001',
            'payment_method_id' => $paymentMethod->id,
            'method_code' => 'cash_on_delivery',
            'method_name' => 'Snapshot Payment',
            'method_type' => 'offline',
            'amount' => '105.0000',
            'currency_code' => 'USD',
            'status' => 'pending',
            'paid_amount' => '0.0000',
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_type' => 'variant',
            'sku' => 'SNAPSHOT-SKU',
            'product_number' => 'SNAPSHOT-NUMBER',
            'name' => 'Snapshot Product',
            'option_summary' => 'Color: Black',
            'quantity' => '1.0000',
            'original_unit_price' => '90.0000',
            'unit_price' => '90.0000',
            'tax_name' => 'Snapshot Tax',
            'tax_rate' => '10.0000',
            'tax_amount' => '9.0000',
            'row_subtotal' => '90.0000',
            'row_total' => '99.0000',
            'unit_cost' => null,
            'is_inventory_item' => true,
        ]);
        $item->options()->create([
            'attribute_code' => 'color', 'attribute_name' => 'Color',
            'option_code' => 'black', 'option_label' => 'Black',
        ]);
    }
}
