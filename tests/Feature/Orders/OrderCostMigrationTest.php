<?php

namespace Tests\Feature\Orders;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderHistoryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OrderCostMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_costs_are_reconstructed_only_from_sale_movements(): void
    {
        $product = $this->product();
        $processed = $this->order(OrderStatus::Processing->value);
        $processedItem = $this->item($processed, $product, null);
        $pending = $this->order(OrderStatus::Pending->value);
        $pendingItem = $this->item($pending, $product, 99);
        $descriptiveItem = $this->item($processed, null, 99, false);
        $processedUpdatedAt = $processedItem->getRawOriginal('updated_at');
        $pendingUpdatedAt = $pendingItem->getRawOriginal('updated_at');

        OrderStatusHistory::create([
            'order_id' => $processed->id,
            'type' => OrderHistoryType::Order->value,
            'from_status' => OrderStatus::Pending->value,
            'to_status' => OrderStatus::Processing->value,
            'created_by' => null,
            'comment' => null,
        ]);
        InventoryMovement::create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_SALE,
            'quantity' => -1,
            'quantity_before' => 5,
            'quantity_after' => 4,
            'unit_cost' => 4.25,
            'total_cost' => -4.25,
            'reference_type' => Order::class,
            'reference_id' => $processed->id,
        ]);

        $this->migration()->up();

        $this->assertEquals(4.25, $processedItem->fresh()->unit_cost);
        $this->assertNull($pendingItem->fresh()->unit_cost);
        $this->assertNull($descriptiveItem->fresh()->unit_cost);
        $this->assertSame(
            $processedUpdatedAt,
            $processedItem->fresh()->getRawOriginal('updated_at')
        );
        $this->assertSame(
            $pendingUpdatedAt,
            $pendingItem->fresh()->getRawOriginal('updated_at')
        );
    }

    public function test_historical_migration_fails_atomically_when_cost_is_not_reconstructable(): void
    {
        $product = $this->product();
        $pending = $this->order(OrderStatus::Pending->value);
        $pendingItem = $this->item($pending, $product, 99);
        $processed = $this->order(OrderStatus::Processing->value);
        $processedItem = $this->item($processed, $product, null);

        OrderStatusHistory::create([
            'order_id' => $processed->id,
            'type' => OrderHistoryType::Order->value,
            'from_status' => OrderStatus::Pending->value,
            'to_status' => OrderStatus::Processing->value,
            'created_by' => null,
            'comment' => null,
        ]);

        try {
            $this->migration()->up();
            $this->fail('An unreconstructable historical cost did not stop the migration.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                "Cannot reconstruct the processing cost for OrderItem {$processedItem->id}: exactly one Sale movement is required.",
                $exception->getMessage()
            );
            $this->assertEquals(99.0, $pendingItem->fresh()->unit_cost);
            $this->assertNull($processedItem->fresh()->unit_cost);
        }
    }

    private function migration(): object
    {
        return require database_path(
            'migrations/2026_07_23_000002_align_order_item_processing_cost_snapshots.php'
        );
    }

    private function product(): Product
    {
        return Product::create([
            'type' => 'simple',
            'sku' => 'SKU-'.uniqid(),
            'price' => 10,
            'is_new' => false,
            'is_featured' => false,
            'is_visible_individually' => true,
            'status' => true,
        ]);
    }

    private function order(string $status): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
            'customer_email' => 'customer@example.com',
            'customer_first_name' => 'Test',
            'customer_last_name' => 'Customer',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => $status,
            'payment_status' => PaymentStatus::Pending->value,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled->value,
            'payment_method' => 'cash',
            'subtotal' => 10,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10,
            'placed_at' => now(),
        ]);
    }

    private function item(
        Order $order,
        ?Product $product,
        ?float $unitCost,
        bool $inventoryItem = true
    ): OrderItem {
        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product?->id,
            'product_type' => $inventoryItem ? 'simple' : 'bundle',
            'sku' => $product?->sku ?? 'BUNDLE-PARENT',
            'name' => 'Snapshot Product',
            'quantity' => 1,
            'original_unit_price' => $inventoryItem ? 10 : 0,
            'unit_price' => $inventoryItem ? 10 : 0,
            'tax_amount' => 0,
            'row_subtotal' => $inventoryItem ? 10 : 0,
            'row_total' => $inventoryItem ? 10 : 0,
            'unit_cost' => $unitCost,
            'is_inventory_item' => $inventoryItem,
        ]);
    }
}
