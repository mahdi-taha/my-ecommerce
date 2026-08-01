<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class OrderSnapshotImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_uses_explicit_mass_assignment_fields(): void
    {
        $order = $this->createOrder(['unapproved_snapshot' => 'ignored']);

        $this->assertFalse(array_key_exists('unapproved_snapshot', $order->getAttributes()));
    }

    public function test_order_checkout_snapshots_cannot_be_updated(): void
    {
        $order = $this->createOrder();

        try {
            $order->update(['grand_total' => '99.0000']);
            $this->fail('An immutable Order snapshot was updated.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('grand_total snapshot is immutable', $exception->getMessage());
            $this->assertEquals(10.0, $order->fresh()->grand_total);
        }
    }

    public function test_order_lifecycle_projection_fields_can_be_updated(): void
    {
        $order = $this->createOrder();
        $cancelledAt = now();

        $order->update([
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
            'fulfillment_status' => 'unfulfilled',
            'cancelled_at' => $cancelledAt,
        ]);

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('cancelled', $fresh->payment_status);
        $this->assertSame(
            $cancelledAt->format('Y-m-d H:i:s'),
            $fresh->cancelled_at->format('Y-m-d H:i:s')
        );
    }

    public function test_order_item_snapshot_fields_cannot_be_updated(): void
    {
        $item = $this->createItem($this->createOrder());

        try {
            $item->update(['unit_price' => '12.0000']);
            $this->fail('An immutable OrderItem snapshot was updated.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('unit_price snapshot is immutable', $exception->getMessage());
            $this->assertEquals(10.0, $item->fresh()->unit_price);
        }
    }

    public function test_order_item_unit_cost_can_be_captured_exactly_once(): void
    {
        $item = $this->createItem($this->createOrder());

        $item->update(['unit_cost' => '4.2500']);
        $this->assertEquals(4.25, $item->fresh()->unit_cost);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('has already been captured');

        $item->fresh()->update(['unit_cost' => '5.0000']);
    }

    public function test_captured_order_item_unit_cost_cannot_be_cleared(): void
    {
        $item = $this->createItem($this->createOrder(), ['unit_cost' => '4.2500']);

        $this->expectException(LogicException::class);

        $item->update(['unit_cost' => null]);
    }

    public function test_order_status_history_is_append_only(): void
    {
        $history = $this->createHistory($this->createOrder());

        try {
            $history->update(['comment' => 'Changed']);
            $this->fail('Order status history was updated.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $history->delete();
    }

    public function test_order_deletion_still_cascades_status_history(): void
    {
        $order = $this->createOrder();
        $history = $this->createHistory($order);

        $order->delete();

        $this->assertDatabaseMissing('order_status_history', ['id' => $history->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function createOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-IMMUTABLE-'.uniqid(),
            'customer_email' => 'customer@example.test',
            'customer_first_name' => 'Snapshot',
            'customer_last_name' => 'Customer',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'subtotal' => '10.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '10.0000',
            'placed_at' => now(),
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function createItem(Order $order, array $overrides = []): OrderItem
    {
        return OrderItem::create(array_merge([
            'order_id' => $order->id,
            'product_type' => 'simple',
            'sku' => 'SNAPSHOT-SKU',
            'name' => 'Snapshot Product',
            'quantity' => '1.0000',
            'original_unit_price' => '10.0000',
            'unit_price' => '10.0000',
            'tax_rate' => '0.0000',
            'tax_amount' => '0.0000',
            'row_subtotal' => '10.0000',
            'discount_amount' => '0.0000',
            'row_total' => '10.0000',
            'unit_cost' => null,
            'is_inventory_item' => true,
        ], $overrides));
    }

    private function createHistory(Order $order): OrderStatusHistory
    {
        return OrderStatusHistory::create([
            'order_id' => $order->id,
            'type' => 'order',
            'from_status' => 'pending',
            'to_status' => 'processing',
            'comment' => 'Processed',
        ]);
    }
}
