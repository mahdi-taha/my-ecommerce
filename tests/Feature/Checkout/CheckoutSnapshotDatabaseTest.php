<?php

namespace Tests\Feature\Checkout;

use App\Models\Order;
use App\Models\OrderItemOption;
use App\Models\OrderShipping;
use App\Models\ShippingMethod;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class CheckoutSnapshotDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_schema_and_relationships_are_available(): void
    {
        $order = $this->createOrder();
        $shippingMethod = ShippingMethod::factory()->create();
        $shipping = $order->shipping()->create($this->shippingSnapshot($shippingMethod));
        $address = $order->addresses()->create($this->addressSnapshot());
        $item = $order->items()->create($this->itemSnapshot());
        $option = $item->options()->create($this->optionSnapshot());

        $this->assertTrue(Schema::hasColumns('order_shipping', [
            'order_id',
            'shipping_method_id',
            'shipping_method_code',
            'shipping_method_name',
            'shipping_method_type',
            'shipping_amount',
        ]));
        $this->assertTrue(Schema::hasColumns('order_items', ['tax_name', 'tax_rate', 'tax_amount']));
        $this->assertTrue(Schema::hasColumns('order_item_options', [
            'order_item_id',
            'attribute_code',
            'attribute_name',
            'option_code',
            'option_label',
        ]));
        $this->assertTrue($shipping->order->is($order));
        $this->assertTrue($shipping->shippingMethod->is($shippingMethod));
        $this->assertTrue($address->order->is($order));
        $this->assertTrue($option->orderItem->is($item));
        $this->assertTrue($order->fresh()->shipping->is($shipping));
        $this->assertTrue($item->fresh()->options->first()->is($option));
        $this->assertSame('11.0000', $item->fresh()->tax_rate);
        $this->assertSame('1.1000', $item->fresh()->tax_amount);
    }

    public function test_snapshot_models_reject_direct_updates_and_deletes(): void
    {
        $order = $this->createOrder();
        $shipping = $order->shipping()->create($this->shippingSnapshot());
        $address = $order->addresses()->create($this->addressSnapshot());
        $item = $order->items()->create($this->itemSnapshot());
        $option = $item->options()->create($this->optionSnapshot());

        foreach ([
            fn () => $shipping->update(['shipping_method_name' => 'Changed']),
            fn () => $shipping->delete(),
            fn () => $address->update(['city' => 'Changed']),
            fn () => $address->delete(),
            fn () => $option->update(['option_label' => 'Changed']),
            fn () => $option->delete(),
            fn () => $item->update(['tax_rate' => '5.0000']),
            fn () => $item->update(['tax_amount' => '0.5000']),
            fn () => $item->update(['tax_name' => 'Changed']),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('An immutable snapshot mutation succeeded.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_owner_cascades_delete_snapshot_rows_without_model_events(): void
    {
        $order = $this->createOrder();
        $order->shipping()->create($this->shippingSnapshot());
        $order->addresses()->create($this->addressSnapshot());
        $item = $order->items()->create($this->itemSnapshot());
        $item->options()->create($this->optionSnapshot());

        $order->delete();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_shipping', 0);
        $this->assertDatabaseCount('order_addresses', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_item_options', 0);
    }

    public function test_shipping_method_deletion_nulls_live_reference_and_preserves_snapshot(): void
    {
        $order = $this->createOrder();
        $shippingMethod = ShippingMethod::factory()->create();
        $shipping = $order->shipping()->create($this->shippingSnapshot($shippingMethod));

        $shippingMethod->delete();
        $shipping->refresh();

        $this->assertNull($shipping->shipping_method_id);
        $this->assertSame('delivery_method', $shipping->shipping_method_code);
        $this->assertSame('Delivery Method', $shipping->shipping_method_name);
        $this->assertSame('delivery', $shipping->shipping_method_type);
        $this->assertSame('2.0000', $shipping->shipping_amount);
    }

    public function test_database_prevents_duplicate_shipping_and_attribute_snapshots(): void
    {
        $order = $this->createOrder();
        $order->shipping()->create($this->shippingSnapshot());
        $item = $order->items()->create($this->itemSnapshot());
        $item->options()->create($this->optionSnapshot());

        try {
            OrderShipping::query()->create(array_merge(
                $this->shippingSnapshot(),
                ['order_id' => $order->id]
            ));
            $this->fail('A duplicate Order shipping snapshot was accepted.');
        } catch (UniqueConstraintViolationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(UniqueConstraintViolationException::class);
        OrderItemOption::query()->create(array_merge(
            $this->optionSnapshot(),
            ['order_item_id' => $item->id]
        ));
    }

    private function createOrder(): Order
    {
        $id = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-2026-'.fake()->unique()->numerify('######'),
            'customer_email' => 'checkout@example.com',
            'customer_first_name' => 'Checkout',
            'customer_last_name' => 'Customer',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'requires_payment_before_processing' => false,
            'subtotal' => '10.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '2.0000',
            'tax_total' => '1.1000',
            'grand_total' => '13.1000',
            'placed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::findOrFail($id);
    }

    private function shippingSnapshot(?ShippingMethod $method = null): array
    {
        return [
            'shipping_method_id' => $method?->id,
            'shipping_method_code' => 'delivery_method',
            'shipping_method_name' => 'Delivery Method',
            'shipping_method_type' => 'delivery',
            'shipping_amount' => '2.0000',
        ];
    }

    private function addressSnapshot(): array
    {
        return [
            'type' => 'billing',
            'first_name' => 'Checkout',
            'last_name' => 'Customer',
            'address_line_1' => 'Test Street',
            'city' => 'Beirut',
            'country_code' => 'LB',
        ];
    }

    private function itemSnapshot(): array
    {
        return [
            'product_type' => 'simple',
            'sku' => 'CHECKOUT-SKU',
            'name' => 'Checkout Product',
            'quantity' => '1.0000',
            'original_unit_price' => '10.0000',
            'unit_price' => '10.0000',
            'tax_name' => 'Standard Tax',
            'tax_rate' => '11.0000',
            'tax_amount' => '1.1000',
            'row_subtotal' => '10.0000',
            'row_total' => '11.1000',
            'unit_cost' => null,
            'is_inventory_item' => false,
        ];
    }

    private function optionSnapshot(): array
    {
        return [
            'attribute_code' => 'color',
            'attribute_name' => 'Color',
            'option_code' => 'black',
            'option_label' => 'Black',
        ];
    }
}
