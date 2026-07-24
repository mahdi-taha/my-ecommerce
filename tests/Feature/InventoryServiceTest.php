<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventoryService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = app(InventoryService::class);
        $this->user = User::factory()->create();
    }

    public function test_opening_stock_sets_inventory_and_creates_one_movement(): void
    {
        $product = $this->simpleProduct();

        $inventory = $this->inventoryService->setOpeningStock($product, [
            'quantity' => 10,
            'unit_cost' => 4.5,
            'notes' => 'Initial count',
        ], $this->user->id);

        $this->assertSame('10.0000', $inventory->quantity);
        $this->assertSame('4.5000', $inventory->average_cost);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OPENING,
            'quantity' => 10,
            'quantity_before' => 0,
            'quantity_after' => 10,
        ]);
    }

    public function test_opening_stock_is_allowed_only_once(): void
    {
        $product = $this->simpleProduct();
        $this->inventoryService->setOpeningStock($product, [
            'quantity' => 5,
            'unit_cost' => 2,
        ], $this->user->id);

        $this->expectException(ValidationException::class);
        $this->inventoryService->setOpeningStock($product, [
            'quantity' => 8,
            'unit_cost' => 3,
        ], $this->user->id);
    }

    public function test_opening_stock_rejects_zero_quantity_without_creating_a_movement(): void
    {
        $product = $this->simpleProduct();

        try {
            $this->inventoryService->setOpeningStock($product, [
                'quantity' => 0,
                'unit_cost' => 2,
            ], $this->user->id);
            $this->fail('Zero opening quantity was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Opening quantity must be greater than zero.',
                $exception->errors()['quantity'][0]
            );
            $this->assertDatabaseMissing('inventory_movements', [
                'product_id' => $product->id,
            ]);
            $this->assertDatabaseMissing('product_inventories', [
                'product_id' => $product->id,
            ]);
        }
    }

    public function test_opening_stock_rejects_zero_cost_without_creating_a_movement(): void
    {
        $product = $this->simpleProduct();

        try {
            $this->inventoryService->setOpeningStock($product, [
                'quantity' => 5,
                'unit_cost' => 0,
            ], $this->user->id);
            $this->fail('Zero opening cost was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Opening unit cost must be greater than zero.',
                $exception->errors()['unit_cost'][0]
            );
            $this->assertDatabaseMissing('inventory_movements', [
                'product_id' => $product->id,
            ]);
            $this->assertDatabaseMissing('product_inventories', [
                'product_id' => $product->id,
            ]);
        }
    }

    public function test_product_without_opening_stock_has_no_opening_movement(): void
    {
        $product = $this->simpleProduct();

        $this->assertDatabaseMissing('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OPENING,
        ]);
    }

    public function test_receipt_updates_weighted_average_cost_and_creates_one_movement(): void
    {
        $product = $this->simpleProduct();
        $this->inventoryService->setOpeningStock($product, ['quantity' => 10, 'unit_cost' => 2], $this->user->id);

        $inventory = $this->inventoryService->receiveStock($product, [
            'quantity' => 10,
            'unit_cost' => 4,
        ], $this->user->id);

        $this->assertSame('20.0000', $inventory->quantity);
        $this->assertSame('3.0000', $inventory->average_cost);
        $this->assertDatabaseCount('inventory_movements', 2);

        $opening = $product->inventoryMovements()
            ->where('type', InventoryMovement::TYPE_OPENING)
            ->firstOrFail();
        $receipt = $product->inventoryMovements()
            ->where('type', InventoryMovement::TYPE_RECEIPT)
            ->firstOrFail();

        $this->assertSame('0.0000', $opening->quantity_before);
        $this->assertSame('10.0000', $opening->quantity_after);
        $this->assertSame('10.0000', $receipt->quantity_before);
        $this->assertSame('20.0000', $receipt->quantity_after);
        $this->assertSame('4.0000', $receipt->unit_cost);
        $this->assertSame('40.0000', $receipt->total_cost);
    }

    public function test_receipt_initializes_one_inventory_row_without_an_opening_movement(): void
    {
        $product = $this->simpleProduct();

        $inventory = $this->inventoryService->receiveStock($product, [
            'quantity' => 3,
            'unit_cost' => 5,
        ], $this->user->id);

        $this->assertSame('3.0000', $inventory->quantity);
        $this->assertDatabaseCount('product_inventories', 1);
        $this->assertDatabaseMissing('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OPENING,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_RECEIPT,
            'quantity_before' => 0,
            'quantity_after' => 3,
        ]);
    }

    public function test_adjustment_cannot_make_quantity_negative(): void
    {
        $product = $this->simpleProduct();
        $this->inventoryService->setOpeningStock($product, ['quantity' => 2, 'unit_cost' => 1], $this->user->id);

        try {
            $this->inventoryService->adjustStock($product, [
                'direction' => 'decrease',
                'quantity' => 3,
                'notes' => 'Damaged',
            ], $this->user->id);
            $this->fail('A negative inventory adjustment was not rejected.');
        } catch (ValidationException) {
            $this->assertSame('2.0000', $product->inventory()->first()->quantity);
            $this->assertDatabaseCount('inventory_movements', 1);
        }
    }

    public function test_adjustment_changes_quantity_without_changing_average_cost(): void
    {
        $product = $this->simpleProduct();
        $this->inventoryService->setOpeningStock($product, ['quantity' => 5, 'unit_cost' => 7], $this->user->id);

        $inventory = $this->inventoryService->adjustStock($product, [
            'direction' => 'increase',
            'quantity' => 2,
            'notes' => 'Found stock',
        ], $this->user->id);

        $this->assertSame('7.0000', $inventory->quantity);
        $this->assertSame('7.0000', $inventory->average_cost);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'quantity_before' => 5,
            'quantity_after' => 7,
        ]);
    }

    public function test_zero_difference_stock_count_still_creates_a_movement(): void
    {
        $product = $this->simpleProduct();
        $this->inventoryService->setOpeningStock($product, ['quantity' => 6, 'unit_cost' => 2], $this->user->id);

        $this->inventoryService->recordStockCount($product, [
            'counted_quantity' => 6,
            'notes' => 'Cycle count',
        ], $this->user->id);

        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_STOCK_COUNT,
            'quantity' => 0,
            'quantity_before' => 6,
            'quantity_after' => 6,
        ]);
    }

    public function test_configurable_and_bundle_parents_are_rejected(): void
    {
        foreach (['configurable', 'bundle'] as $type) {
            $product = $this->product($type);

            try {
                $this->inventoryService->receiveStock($product, ['quantity' => 1, 'unit_cost' => 1], $this->user->id);
                $this->fail("A {$type} parent was allowed to own inventory.");
            } catch (ValidationException) {
                $this->assertDatabaseMissing('product_inventories', ['product_id' => $product->id]);
            }
        }
    }

    public function test_configurable_variant_is_inventory_eligible(): void
    {
        $parent = $this->product('configurable');
        $variant = $this->product('simple');
        $variant->update(['configurable_id' => $parent->id]);

        $inventory = $this->inventoryService->receiveStock(
            $variant,
            ['quantity' => 3, 'unit_cost' => 5],
            $this->user->id
        );

        $this->assertSame('3.0000', $inventory->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $variant->id,
            'type' => InventoryMovement::TYPE_RECEIPT,
        ]);
    }

    public function test_available_quantity_is_the_on_hand_quantity(): void
    {
        $product = $this->simpleProduct();
        $inventory = $this->inventoryService->setOpeningStock(
            $product,
            ['quantity' => 7.25, 'unit_cost' => 2],
            $this->user->id
        );

        $this->assertSame('7.2500', $inventory->availableQuantity());
    }

    public function test_low_stock_threshold_is_owned_and_updated_by_inventory_service(): void
    {
        $product = $this->simpleProduct();

        $inventory = $this->inventoryService->updateLowStockAlert($product, 2.5);

        $this->assertSame('2.5000', $inventory->low_stock_alert);
        $this->assertDatabaseHas('product_inventories', [
            'product_id' => $product->id,
            'low_stock_alert' => 2.5,
        ]);
    }

    public function test_existing_movements_remain_unchanged_after_later_operations(): void
    {
        $product = $this->simpleProduct();
        $this->inventoryService->setOpeningStock(
            $product,
            ['quantity' => 5, 'unit_cost' => 3, 'notes' => 'Initial count'],
            $this->user->id
        );
        $opening = $product->inventoryMovements()->firstOrFail();
        $snapshot = $opening->only([
            'type',
            'quantity',
            'quantity_before',
            'quantity_after',
            'unit_cost',
            'total_cost',
            'notes',
        ]);
        $updatedAt = $opening->getRawOriginal('updated_at');

        $this->inventoryService->receiveStock(
            $product,
            ['quantity' => 2, 'unit_cost' => 4],
            $this->user->id
        );

        $this->assertSame($snapshot, $opening->fresh()->only(array_keys($snapshot)));
        $this->assertSame($updatedAt, $opening->fresh()->getRawOriginal('updated_at'));
    }

    private function simpleProduct(): Product
    {
        return $this->product('simple');
    }

    private function product(string $type): Product
    {
        return Product::create([
            'type' => $type,
            'sku' => strtoupper($type).'-'.uniqid(),
            'price' => 0,
            'is_new' => false,
            'is_featured' => false,
            'is_visible_individually' => true,
            'status' => false,
        ]);
    }
}
