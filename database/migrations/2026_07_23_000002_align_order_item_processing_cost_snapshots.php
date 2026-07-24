<?php

use App\Enums\OrderStatus;
use App\Models\InventoryMovement;
use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $items = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->select([
                    'order_items.id',
                    'order_items.order_id',
                    'order_items.product_id',
                    'order_items.is_inventory_item',
                    'orders.status as order_status',
                ])
                ->orderBy('order_items.id')
                ->get();

            $processedOrderIds = DB::table('order_status_history')
                ->where('type', 'order')
                ->where('from_status', OrderStatus::Pending->value)
                ->where('to_status', OrderStatus::Processing->value)
                ->pluck('order_id')
                ->mapWithKeys(fn ($orderId) => [(int) $orderId => true])
                ->all();

            $updates = [];

            foreach ($items as $item) {
                if (! $item->is_inventory_item) {
                    $updates[(int) $item->id] = null;

                    continue;
                }

                $wasProcessed = isset($processedOrderIds[(int) $item->order_id])
                    || in_array($item->order_status, [
                        OrderStatus::Processing->value,
                        OrderStatus::Completed->value,
                    ], true);

                $saleMovements = collect();

                if ($item->product_id !== null) {
                    $saleMovements = DB::table('inventory_movements')
                        ->where('product_id', $item->product_id)
                        ->where('type', InventoryMovement::TYPE_SALE)
                        ->where('reference_type', Order::class)
                        ->where('reference_id', $item->order_id)
                        ->get(['id', 'unit_cost']);
                }

                if ($saleMovements->isNotEmpty()) {
                    $wasProcessed = true;
                }

                if (! $wasProcessed) {
                    $updates[(int) $item->id] = null;

                    continue;
                }

                if ($item->product_id === null || $saleMovements->count() !== 1) {
                    throw new RuntimeException(
                        "Cannot reconstruct the processing cost for OrderItem {$item->id}: exactly one Sale movement is required."
                    );
                }

                $unitCost = $saleMovements->first()->unit_cost;

                if ($unitCost === null) {
                    throw new RuntimeException(
                        "Cannot reconstruct the processing cost for OrderItem {$item->id}: the Sale movement has no unit cost."
                    );
                }

                $updates[(int) $item->id] = $unitCost;
            }

            foreach ($updates as $itemId => $unitCost) {
                DB::table('order_items')
                    ->where('id', $itemId)
                    ->update(['unit_cost' => $unitCost]);
            }
        });
    }

    public function down(): void
    {
        // Historical processing cost snapshots cannot be safely reversed.
    }
};
