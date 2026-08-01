<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderModelCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_monetary_and_lifecycle_values_use_stable_casts(): void
    {
        $order = $this->createOrder([
            'subtotal' => '12.3',
            'discount_total' => '1',
            'shipping_total' => '2.5',
            'tax_total' => '1.353',
            'grand_total' => '15.153',
            'placed_at' => '2026-08-01 12:34:56',
            'paid_at' => '2026-08-01 13:00:00',
            'cancelled_at' => null,
            'completed_at' => '2026-08-02 14:15:16',
        ])->fresh();

        $this->assertSame('12.3000', $order->subtotal);
        $this->assertSame('1.0000', $order->discount_total);
        $this->assertSame('2.5000', $order->shipping_total);
        $this->assertSame('1.3530', $order->tax_total);
        $this->assertSame('15.1530', $order->grand_total);
        $this->assertInstanceOf(CarbonInterface::class, $order->placed_at);
        $this->assertInstanceOf(CarbonInterface::class, $order->paid_at);
        $this->assertNull($order->cancelled_at);
        $this->assertInstanceOf(CarbonInterface::class, $order->completed_at);

        $serialized = json_decode($order->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('12.3000', $serialized['subtotal']);
        $this->assertSame('15.1530', $serialized['grand_total']);
        $this->assertSame('2026-08-01 12:34:56', $serialized['placed_at']);
        $this->assertSame('2026-08-01 13:00:00', $serialized['paid_at']);
        $this->assertNull($serialized['cancelled_at']);
        $this->assertSame('2026-08-02 14:15:16', $serialized['completed_at']);
    }

    public function test_order_item_decimal_values_are_four_decimal_strings_and_keep_nullability(): void
    {
        $order = $this->createOrder();
        $item = $this->createItem($order, [
            'quantity' => '2',
            'original_unit_price' => '15',
            'unit_price' => '12.345',
            'tax_rate' => '11',
            'tax_amount' => '2.7159',
            'row_subtotal' => '24.69',
            'discount_amount' => '1',
            'row_total' => '26.4059',
            'unit_cost' => '4.5',
        ])->fresh();
        $itemWithoutCost = $this->createItem($order, ['unit_cost' => null])->fresh();

        $this->assertSame('2.0000', $item->quantity);
        $this->assertSame('15.0000', $item->original_unit_price);
        $this->assertSame('12.3450', $item->unit_price);
        $this->assertSame('11.0000', $item->tax_rate);
        $this->assertSame('2.7159', $item->tax_amount);
        $this->assertSame('24.6900', $item->row_subtotal);
        $this->assertSame('1.0000', $item->discount_amount);
        $this->assertSame('26.4059', $item->row_total);
        $this->assertSame('4.5000', $item->unit_cost);
        $this->assertNull($itemWithoutCost->unit_cost);

        $serialized = json_decode($item->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('2.0000', $serialized['quantity']);
        $this->assertSame('12.3450', $serialized['unit_price']);
        $this->assertSame('4.5000', $serialized['unit_cost']);
        $this->assertEquals(24.69, (float) $item->unit_price * (float) $item->quantity);
    }

    /** @param array<string, mixed> $overrides */
    private function createOrder(array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'order_number' => 'ORD-CAST-'.uniqid(),
            'customer_email' => 'cast@example.test',
            'customer_first_name' => 'Cast',
            'customer_last_name' => 'Customer',
            'customer_phone' => null,
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
            'placed_at' => '2026-08-01 10:00:00',
            'paid_at' => null,
            'cancelled_at' => null,
            'completed_at' => null,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function createItem(Order $order, array $overrides = []): OrderItem
    {
        return $order->items()->create(array_merge([
            'product_type' => 'simple',
            'sku' => 'CAST-'.uniqid(),
            'name' => 'Cast Product',
            'quantity' => '1.0000',
            'original_unit_price' => '10.0000',
            'unit_price' => '10.0000',
            'tax_name' => null,
            'tax_rate' => '0.0000',
            'tax_amount' => '0.0000',
            'row_subtotal' => '10.0000',
            'discount_amount' => '0.0000',
            'row_total' => '10.0000',
            'unit_cost' => null,
            'is_inventory_item' => true,
        ], $overrides));
    }
}
