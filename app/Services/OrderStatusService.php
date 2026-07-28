<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderHistoryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderStatusService
{
    public function __construct(
        private InventoryService $inventoryService,
        private OrderCompletionService $orderCompletionService
    ) {}

    public function process(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            if (! $order->getKey()) {
                throw new RuntimeException('The order does not exist.');
            }

            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder) {
                throw new RuntimeException('The order no longer exists.');
            }

            if ($lockedOrder->status !== OrderStatus::Pending->value) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending orders can be moved to processing.',
                ]);
            }

            if ($lockedOrder->requires_payment_before_processing
                && $lockedOrder->payment_status !== PaymentStatus::Paid->value) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Payment is required before this order can be processed.',
                ]);
            }

            $userId = auth()->id();
            $items = $this->inventoryItems($lockedOrder, true);
            $this->ensureCostsAreNotCaptured($items);
            $requirements = $this->inventoryRequirements($items);

            $productCosts = $this->inventoryService->deductOrderStock(
                $lockedOrder,
                $requirements,
                $userId
            );

            $this->persistInventoryCosts($items, $requirements, $productCosts);

            $lockedOrder->update([
                'status' => OrderStatus::Processing->value,
            ]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->getKey(),
                'type' => OrderHistoryType::Order->value,
                'from_status' => OrderStatus::Pending->value,
                'to_status' => OrderStatus::Processing->value,
                'created_by' => $userId,
                'comment' => null,
            ]);

            return $lockedOrder->fresh();
        });
    }

    public function markOutForDelivery(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = $this->lockedOrder($order);

            if ($lockedOrder->status !== OrderStatus::Processing->value) {
                throw ValidationException::withMessages([
                    'status' => 'Only processing orders can be marked out for delivery.',
                ]);
            }

            if ($lockedOrder->fulfillment_status !== FulfillmentStatus::Unfulfilled->value) {
                throw ValidationException::withMessages([
                    'fulfillment_status' => 'Only unfulfilled orders can be marked out for delivery.',
                ]);
            }

            $userId = auth()->id();

            if (! $lockedOrder->update([
                'fulfillment_status' => FulfillmentStatus::OutForDelivery->value,
            ])) {
                throw new RuntimeException('The order fulfillment status could not be updated.');
            }

            $this->createHistory(
                $lockedOrder,
                OrderHistoryType::Fulfillment->value,
                FulfillmentStatus::Unfulfilled->value,
                FulfillmentStatus::OutForDelivery->value,
                $userId
            );

            return $lockedOrder->fresh();
        });
    }

    public function fulfill(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = $this->lockedOrder($order);

            if ($lockedOrder->status !== OrderStatus::Processing->value) {
                throw ValidationException::withMessages([
                    'status' => 'Only processing orders can be fulfilled.',
                ]);
            }

            if ($lockedOrder->fulfillment_status !== FulfillmentStatus::OutForDelivery->value) {
                throw ValidationException::withMessages([
                    'fulfillment_status' => 'Only orders that are out for delivery can be fulfilled.',
                ]);
            }

            $userId = auth()->id();

            if (! $lockedOrder->update([
                'fulfillment_status' => FulfillmentStatus::Fulfilled->value,
            ])) {
                throw new RuntimeException('The order fulfillment status could not be updated.');
            }

            $this->createHistory(
                $lockedOrder,
                OrderHistoryType::Fulfillment->value,
                FulfillmentStatus::OutForDelivery->value,
                FulfillmentStatus::Fulfilled->value,
                $userId
            );

            $this->orderCompletionService->completeIfEligible($lockedOrder, $userId);

            return $lockedOrder->fresh();
        });
    }

    public function markDeliveryFailed(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = $this->lockedOrder($order);

            if ($lockedOrder->status !== OrderStatus::Processing->value) {
                throw ValidationException::withMessages([
                    'status' => 'Only processing orders can be marked as delivery failed.',
                ]);
            }

            if ($lockedOrder->fulfillment_status !== FulfillmentStatus::OutForDelivery->value) {
                throw ValidationException::withMessages([
                    'fulfillment_status' => 'Only orders that are out for delivery can be marked as delivery failed.',
                ]);
            }

            $userId = auth()->id();
            $requirements = $this->inventoryRequirements(
                $this->inventoryItems($lockedOrder)
            );

            $this->inventoryService->restoreOrderStock(
                $lockedOrder,
                $requirements,
                $userId
            );

            if ($lockedOrder->payment_status !== PaymentStatus::Paid->value) {
                $this->cancelPayment($lockedOrder, $userId);
            }

            if (! $lockedOrder->update([
                'status' => OrderStatus::Cancelled->value,
                'fulfillment_status' => FulfillmentStatus::DeliveryFailed->value,
                'cancelled_at' => now(),
            ])) {
                throw new RuntimeException('The order delivery failure could not be recorded.');
            }

            $this->createHistory(
                $lockedOrder,
                OrderHistoryType::Fulfillment->value,
                FulfillmentStatus::OutForDelivery->value,
                FulfillmentStatus::DeliveryFailed->value,
                $userId
            );
            $this->createHistory(
                $lockedOrder,
                OrderHistoryType::Order->value,
                OrderStatus::Processing->value,
                OrderStatus::Cancelled->value,
                $userId
            );

            return $lockedOrder->fresh();
        });
    }

    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = $this->lockedOrder($order);

            if ($lockedOrder->payment_status === PaymentStatus::Paid->value) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Paid orders cannot be cancelled without a refund.',
                ]);
            }

            if ($lockedOrder->status === OrderStatus::Completed->value) {
                throw ValidationException::withMessages([
                    'status' => 'Completed orders cannot be cancelled.',
                ]);
            }

            if ($lockedOrder->status === OrderStatus::Cancelled->value) {
                throw ValidationException::withMessages([
                    'status' => 'The order is already cancelled.',
                ]);
            }

            if (! in_array($lockedOrder->status, [
                OrderStatus::Pending->value,
                OrderStatus::Processing->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending or processing orders can be cancelled.',
                ]);
            }

            $fromStatus = $lockedOrder->status;
            $userId = auth()->id();

            if ($lockedOrder->fulfillment_status !== FulfillmentStatus::Unfulfilled->value) {
                throw ValidationException::withMessages([
                    'fulfillment_status' => 'Orders cannot be cancelled after they are out for delivery.',
                ]);
            }

            if ($fromStatus === OrderStatus::Processing->value) {
                $this->inventoryService->restoreOrderStock(
                    $lockedOrder,
                    $this->inventoryRequirements($this->inventoryItems($lockedOrder)),
                    $userId
                );
            }

            $this->cancelPayment($lockedOrder, $userId);

            if (! $lockedOrder->update([
                'status' => OrderStatus::Cancelled->value,
                'cancelled_at' => now(),
            ])) {
                throw new RuntimeException('The order could not be cancelled.');
            }

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->getKey(),
                'type' => OrderHistoryType::Order->value,
                'from_status' => $fromStatus,
                'to_status' => OrderStatus::Cancelled->value,
                'created_by' => $userId,
                'comment' => null,
            ]);

            return $lockedOrder->fresh();
        });
    }

    private function lockedOrder(Order $order): Order
    {
        if (! $order->getKey()) {
            throw new RuntimeException('The order does not exist.');
        }

        $lockedOrder = Order::query()
            ->whereKey($order->getKey())
            ->lockForUpdate()
            ->first();

        if (! $lockedOrder) {
            throw new RuntimeException('The order no longer exists.');
        }

        return $lockedOrder;
    }

    private function inventoryItems(Order $order, bool $lock = false): Collection
    {
        $query = OrderItem::query()
            ->where('order_id', $order->getKey())
            ->where('is_inventory_item', true)
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get(['id', 'product_id', 'quantity', 'unit_cost']);
    }

    private function inventoryRequirements(Collection $items): array
    {
        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'The order does not contain any inventory-bearing items.',
            ]);
        }

        $requirements = [];

        foreach ($items as $item) {
            if ($item->product_id === null) {
                throw ValidationException::withMessages([
                    'items' => "Order item {$item->id} no longer references a product.",
                ]);
            }

            $productId = (int) $item->product_id;
            $requirements[$productId] = round(
                ($requirements[$productId] ?? 0) + (float) $item->quantity,
                4
            );
        }

        if (empty($requirements)) {
            throw ValidationException::withMessages([
                'items' => 'No inventory requirements could be calculated for the order.',
            ]);
        }

        return $requirements;
    }

    private function ensureCostsAreNotCaptured(Collection $items): void
    {
        foreach ($items as $item) {
            if ($item->unit_cost !== null) {
                throw ValidationException::withMessages([
                    'items' => "Order item {$item->id} already has an inventory cost snapshot.",
                ]);
            }
        }
    }

    private function persistInventoryCosts(
        Collection $items,
        array $requirements,
        array $productCosts
    ): void {
        foreach (array_keys($requirements) as $productId) {
            if (! array_key_exists($productId, $productCosts)) {
                throw new RuntimeException(
                    "Inventory deduction did not return a cost for product {$productId}."
                );
            }
        }

        foreach ($items as $item) {
            $productId = (int) $item->product_id;
            $updated = OrderItem::query()
                ->whereKey($item->id)
                ->whereNull('unit_cost')
                ->update(['unit_cost' => $productCosts[$productId]]);

            if ($updated !== 1) {
                throw new RuntimeException(
                    "The inventory cost for OrderItem {$item->id} could not be captured."
                );
            }
        }
    }

    private function cancelPayment(Order $order, ?int $userId): void
    {
        if ($order->payment_status === PaymentStatus::Paid->value
            || $order->payment_status === PaymentStatus::Cancelled->value) {
            return;
        }

        $fromStatus = $order->payment_status;
        $payment = OrderPayment::query()
            ->where('order_id', $order->getKey())
            ->lockForUpdate()
            ->first();

        if (! $payment) {
            throw new RuntimeException('The order does not have a payment obligation to cancel.');
        }

        if ($fromStatus === PaymentStatus::Pending->value) {
            $attempt = $payment->attempts()
                ->whereIn('status', [
                    PaymentAttemptStatus::Pending->value,
                    PaymentAttemptStatus::RequiresAction->value,
                    PaymentAttemptStatus::Processing->value,
                ])
                ->latest('attempt_number')
                ->lockForUpdate()
                ->first();

            if ($attempt && ! $attempt->update([
                'status' => PaymentAttemptStatus::Cancelled,
                'completed_at' => now(),
            ])) {
                throw new RuntimeException('The pending payment attempt could not be cancelled.');
            }
        }

        if (! $payment->update([
            'status' => PaymentStatus::Cancelled,
            'paid_amount' => '0.0000',
            'paid_at' => null,
        ])) {
            throw new RuntimeException('The payment obligation could not be cancelled.');
        }

        if (! $order->update(['payment_status' => PaymentStatus::Cancelled->value])) {
            throw new RuntimeException('The order payment status could not be cancelled.');
        }

        $this->createHistory(
            $order,
            OrderHistoryType::Payment->value,
            $fromStatus,
            PaymentStatus::Cancelled->value,
            $userId
        );
    }

    private function createHistory(
        Order $order,
        string $type,
        ?string $fromStatus,
        string $toStatus,
        ?int $userId
    ): void {
        OrderStatusHistory::create([
            'order_id' => $order->getKey(),
            'type' => $type,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'created_by' => $userId,
            'comment' => null,
        ]);
    }
}
