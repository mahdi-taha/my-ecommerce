<?php

namespace Tests\Feature\Orders;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderHistoryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderCompletionService;
use App\Services\OrderStatusService;
use App\Services\PaymentStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatusService $orderStatusService;

    private PaymentStatusService $paymentStatusService;

    private OrderCompletionService $orderCompletionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderStatusService = app(OrderStatusService::class);
        $this->paymentStatusService = app(PaymentStatusService::class);
        $this->orderCompletionService = app(OrderCompletionService::class);
    }

    public function test_pending_order_can_be_processed_atomically(): void
    {
        [$order, $product] = $this->orderWithInventory(10, [1.25, 1.5]);

        $processed = $this->orderStatusService->process($order);

        $this->assertSame(OrderStatus::Processing->value, $processed->status);
        $this->assertSame(PaymentStatus::Pending->value, $processed->payment_status);
        $this->assertSame(FulfillmentStatus::Unfulfilled->value, $processed->fulfillment_status);
        $this->assertSame('7.2500', $product->inventory()->first()->quantity);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_SALE,
            'quantity' => -2.75,
            'quantity_before' => 10,
            'quantity_after' => 7.25,
            'unit_cost' => 4,
            'total_cost' => -11,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
        ]);
        $this->assertEquals(
            [4.0],
            $order->items()->distinct()->pluck('unit_cost')->all()
        );
        $this->assertDatabaseCount('order_status_history', 1);
        $this->assertHistory($order, OrderHistoryType::Order->value, 'pending', 'processing');
    }

    public function test_insufficient_stock_rolls_back_processing_completely(): void
    {
        [$order, $product] = $this->orderWithInventory(1, [1.5]);

        try {
            $this->orderStatusService->process($order);
            $this->fail('Insufficient stock was not rejected.');
        } catch (ValidationException) {
            $this->assertSame(OrderStatus::Pending->value, $order->fresh()->status);
            $this->assertSame('1.0000', $product->inventory()->first()->quantity);
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertDatabaseCount('order_status_history', 0);
            $this->assertSame(1, $order->items()->whereNull('unit_cost')->count());
        }
    }

    public function test_duplicate_product_rows_receive_the_same_processing_cost(): void
    {
        [$order] = $this->orderWithInventory(10, [1.25, 1.5]);

        $this->orderStatusService->process($order);

        $this->assertEquals(
            [4.0, 4.0],
            $order->items()->orderBy('id')->pluck('unit_cost')->all()
        );
        $this->assertSame(1, InventoryMovement::query()
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', InventoryMovement::TYPE_SALE)
            ->count());
    }

    public function test_bundle_parent_remains_without_cost_while_child_receives_cost(): void
    {
        [$order] = $this->orderWithInventory();
        $child = $order->items()->firstOrFail();
        $parent = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => null,
            'product_type' => 'bundle',
            'sku' => 'BUNDLE-PARENT',
            'name' => 'Bundle Parent',
            'quantity' => 1,
            'original_unit_price' => 0,
            'unit_price' => 0,
            'tax_amount' => 0,
            'row_subtotal' => 0,
            'row_total' => 0,
            'unit_cost' => null,
            'is_inventory_item' => false,
        ]);
        $child->update([
            'parent_order_item_id' => $parent->id,
            'product_type' => 'bundle_item',
        ]);

        $this->orderStatusService->process($order);

        $this->assertNull($parent->fresh()->unit_cost);
        $this->assertEquals(4.0, $child->fresh()->unit_cost);
    }

    public function test_existing_inventory_cost_blocks_processing_without_partial_changes(): void
    {
        [$order, $product] = $this->orderWithInventory();
        $order->items()->firstOrFail()->update(['unit_cost' => 3]);

        try {
            $this->orderStatusService->process($order);
            $this->fail('An existing inventory cost snapshot was overwritten.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'already has an inventory cost snapshot',
                $exception->errors()['items'][0]
            );
            $this->assertSame(OrderStatus::Pending->value, $order->fresh()->status);
            $this->assertSame('10.0000', $product->inventory()->firstOrFail()->quantity);
            $this->assertEquals(3.0, $order->items()->firstOrFail()->unit_cost);
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertDatabaseCount('order_status_history', 0);
        }
    }

    public function test_missing_returned_product_cost_rolls_back_processing(): void
    {
        [$order, $product] = $this->orderWithInventory();
        $inventoryService = new class extends InventoryService
        {
            public function deductOrderStock(
                Order $order,
                array $requirements,
                ?int $userId = null
            ): array {
                parent::deductOrderStock($order, $requirements, $userId);

                return [];
            }
        };
        $service = new OrderStatusService(
            $inventoryService,
            $this->orderCompletionService
        );

        try {
            $service->process($order);
            $this->fail('Processing accepted an incomplete inventory cost map.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                "Inventory deduction did not return a cost for product {$product->id}.",
                $exception->getMessage()
            );
            $this->assertSame(OrderStatus::Pending->value, $order->fresh()->status);
            $this->assertNull($order->items()->firstOrFail()->unit_cost);
            $this->assertSame('10.0000', $product->inventory()->firstOrFail()->quantity);
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertDatabaseCount('order_status_history', 0);
        }
    }

    public function test_later_receipt_does_not_change_processing_cost_snapshot(): void
    {
        [$order, $product] = $this->orderWithInventory();
        $this->orderStatusService->process($order);
        $snapshot = $order->items()->firstOrFail()->unit_cost;

        app(InventoryService::class)->receiveStock(
            $product,
            ['quantity' => 10, 'unit_cost' => 8],
            User::factory()->create()->id
        );

        $this->assertEquals(4.0, $snapshot);
        $this->assertSame($snapshot, $order->items()->firstOrFail()->fresh()->unit_cost);
        $this->assertNotEquals(
            $snapshot,
            (float) $product->inventory()->firstOrFail()->average_cost
        );
    }

    public function test_return_uses_original_sale_cost_after_average_cost_changes(): void
    {
        [$order, $product] = $this->orderWithInventory();
        $this->orderStatusService->process($order);
        app(InventoryService::class)->receiveStock(
            $product,
            ['quantity' => 10, 'unit_cost' => 8],
            User::factory()->create()->id
        );

        $this->orderStatusService->cancel($order);

        $return = InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_RETURN)
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->firstOrFail();
        $this->assertSame('4.0000', $return->unit_cost);
        $this->assertSame('8.0000', $return->total_cost);
        $this->assertNotSame(
            $return->unit_cost,
            $product->inventory()->firstOrFail()->average_cost
        );
    }

    public function test_prepayment_required_order_cannot_process_until_paid(): void
    {
        [$order, $product] = $this->orderWithInventory();
        $order->update(['requires_payment_before_processing' => true]);

        try {
            $this->orderStatusService->process($order);
            $this->fail('An unpaid prepayment-required order was processed.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Payment is required before this order can be processed.'],
                $exception->errors()['payment_status']
            );
            $this->assertSame(OrderStatus::Pending->value, $order->fresh()->status);
            $this->assertSame('10.0000', $product->inventory()->first()->quantity);
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertDatabaseCount('order_status_history', 0);
        }

        $this->paymentStatusService->markPaid($order);
        $processed = $this->orderStatusService->process($order);

        $this->assertSame(OrderStatus::Processing->value, $processed->status);
        $this->assertSame('8.0000', $product->inventory()->first()->quantity);
    }

    public function test_out_for_delivery_requires_processing_and_does_not_change_inventory(): void
    {
        [$order, $product] = $this->orderWithInventory();

        try {
            $this->orderStatusService->markOutForDelivery($order);
            $this->fail('A pending order was marked out for delivery.');
        } catch (ValidationException) {
            $this->assertSame(FulfillmentStatus::Unfulfilled->value, $order->fresh()->fulfillment_status);
        }

        $this->orderStatusService->process($order);
        $dispatched = $this->orderStatusService->markOutForDelivery($order);

        $this->assertSame(FulfillmentStatus::OutForDelivery->value, $dispatched->fulfillment_status);
        $this->assertSame('8.0000', $product->inventory()->first()->quantity);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertHistory(
            $order,
            OrderHistoryType::Fulfillment->value,
            FulfillmentStatus::Unfulfilled->value,
            FulfillmentStatus::OutForDelivery->value
        );
    }

    public function test_order_cannot_be_fulfilled_before_it_is_out_for_delivery(): void
    {
        [$order] = $this->orderWithInventory();
        $this->orderStatusService->process($order);

        try {
            $this->orderStatusService->fulfill($order);
            $this->fail('An unfulfilled order was fulfilled without dispatch.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Only orders that are out for delivery can be fulfilled.'],
                $exception->errors()['fulfillment_status']
            );
            $this->assertSame(FulfillmentStatus::Unfulfilled->value, $order->fresh()->fulfillment_status);
        }
    }

    public function test_paid_then_fulfilled_order_completes(): void
    {
        [$order] = $this->orderWithInventory();

        $this->orderStatusService->process($order);
        $this->paymentStatusService->markPaid($order);
        $this->orderStatusService->markOutForDelivery($order);
        $completed = $this->orderStatusService->fulfill($order);

        $this->assertCompletedOrder($completed);
        $this->assertDatabaseCount('order_status_history', 5);
        $this->assertCompletionHistoryCount($order, 1);
    }

    public function test_fulfilled_then_paid_order_completes(): void
    {
        [$order] = $this->orderWithInventory();

        $this->orderStatusService->process($order);
        $this->orderStatusService->markOutForDelivery($order);
        $fulfilled = $this->orderStatusService->fulfill($order);
        $this->assertSame(OrderStatus::Processing->value, $fulfilled->status);
        $this->assertNull($fulfilled->completed_at);

        $completed = $this->paymentStatusService->markPaid($order);

        $this->assertCompletedOrder($completed);
        $this->assertDatabaseCount('order_status_history', 5);
        $this->assertCompletionHistoryCount($order, 1);
    }

    public function test_completion_is_idempotent_inside_an_active_transaction(): void
    {
        [$order] = $this->orderWithInventory();

        $this->orderStatusService->process($order);
        $this->paymentStatusService->markPaid($order);
        $this->orderStatusService->markOutForDelivery($order);
        $completed = $this->orderStatusService->fulfill($order);
        $completedAt = $completed->completed_at;

        DB::transaction(function () use ($completed): void {
            $this->orderCompletionService->completeIfEligible($completed->fresh());
        });

        $this->assertSame($completedAt, $completed->fresh()->completed_at);
        $this->assertCompletionHistoryCount($order, 1);
    }

    public function test_pending_unpaid_order_can_be_cancelled_without_inventory_change(): void
    {
        [$order, $product] = $this->orderWithInventory(8);

        $cancelled = $this->orderStatusService->cancel($order);

        $this->assertSame(OrderStatus::Cancelled->value, $cancelled->status);
        $this->assertSame(PaymentStatus::Cancelled->value, $cancelled->payment_status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('8.0000', $product->inventory()->first()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertHistory($order, OrderHistoryType::Order->value, 'pending', 'cancelled');
        $this->assertHistory($order, OrderHistoryType::Payment->value, 'pending', 'cancelled');
        $this->assertSame(PaymentStatus::Cancelled->value, $order->payments()->first()->status);
    }

    public function test_processing_order_cancellation_restores_inventory(): void
    {
        [$order, $product] = $this->orderWithInventory(8, [2.5]);

        $this->orderStatusService->process($order);
        $cancelled = $this->orderStatusService->cancel($order);

        $this->assertSame(OrderStatus::Cancelled->value, $cancelled->status);
        $this->assertSame(PaymentStatus::Cancelled->value, $cancelled->payment_status);
        $this->assertSame('8.0000', $product->inventory()->first()->quantity);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_SALE,
            'quantity' => -2.5,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_RETURN,
            'quantity' => 2.5,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
        ]);
        $this->assertHistory($order, OrderHistoryType::Order->value, 'processing', 'cancelled');
        $this->assertHistory($order, OrderHistoryType::Payment->value, 'pending', 'cancelled');
    }

    public function test_unpaid_delivery_failure_restores_inventory_and_cancels_payment(): void
    {
        [$order, $product] = $this->orderWithInventory(8, [2]);
        $this->orderStatusService->process($order);
        $this->orderStatusService->markOutForDelivery($order);

        $failed = $this->orderStatusService->markDeliveryFailed($order);

        $this->assertSame(OrderStatus::Cancelled->value, $failed->status);
        $this->assertSame(PaymentStatus::Cancelled->value, $failed->payment_status);
        $this->assertSame(FulfillmentStatus::DeliveryFailed->value, $failed->fulfillment_status);
        $this->assertSame('8.0000', $product->inventory()->first()->quantity);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_RETURN,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
        ]);
        $this->assertHistory($order, OrderHistoryType::Payment->value, 'pending', 'cancelled');
        $this->assertHistory($order, OrderHistoryType::Fulfillment->value, 'out_for_delivery', 'delivery_failed');
        $this->assertHistory($order, OrderHistoryType::Order->value, 'processing', 'cancelled');

        try {
            $this->orderStatusService->markDeliveryFailed($order);
            $this->fail('A delivery failure was recorded twice.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inventory_movements', 2);
            $this->assertSame('8.0000', $product->inventory()->first()->quantity);
        }
    }

    public function test_paid_delivery_failure_keeps_payment_paid_and_restores_inventory(): void
    {
        [$order, $product] = $this->orderWithInventory(8, [2]);
        $this->orderStatusService->process($order);
        $this->paymentStatusService->markPaid($order);
        $payment = $order->payments()->firstOrFail()->fresh();
        $this->orderStatusService->markOutForDelivery($order);

        $failed = $this->orderStatusService->markDeliveryFailed($order);

        $this->assertSame(OrderStatus::Cancelled->value, $failed->status);
        $this->assertSame(PaymentStatus::Paid->value, $failed->payment_status);
        $this->assertSame(FulfillmentStatus::DeliveryFailed->value, $failed->fulfillment_status);
        $this->assertSame(PaymentStatus::Paid->value, $payment->fresh()->status);
        $this->assertSame($payment->paid_at, $payment->fresh()->paid_at);
        $this->assertSame('8.0000', $product->inventory()->first()->quantity);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertSame(0, OrderStatusHistory::query()
            ->where('order_id', $order->id)
            ->where('type', OrderHistoryType::Payment->value)
            ->where('to_status', PaymentStatus::Cancelled->value)
            ->count());
    }

    public function test_order_cannot_be_normally_cancelled_after_dispatch(): void
    {
        [$order, $product] = $this->orderWithInventory();
        $this->orderStatusService->process($order);
        $this->orderStatusService->markOutForDelivery($order);

        try {
            $this->orderStatusService->cancel($order);
            $this->fail('A dispatched order was cancelled through normal cancellation.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Orders cannot be cancelled after they are out for delivery.'],
                $exception->errors()['fulfillment_status']
            );
            $this->assertSame(OrderStatus::Processing->value, $order->fresh()->status);
            $this->assertSame(FulfillmentStatus::OutForDelivery->value, $order->fresh()->fulfillment_status);
            $this->assertSame('8.0000', $product->inventory()->first()->quantity);
            $this->assertDatabaseCount('inventory_movements', 1);
        }
    }

    public function test_cancelling_failed_payment_preserves_attempt_and_cancels_aggregate_status(): void
    {
        [$order] = $this->orderWithInventory();
        $this->paymentStatusService->markFailed($order);
        $failedPayment = $order->payments()->firstOrFail()->fresh();

        $cancelled = $this->orderStatusService->cancel($order);

        $this->assertSame(PaymentStatus::Cancelled->value, $cancelled->payment_status);
        $this->assertSame(PaymentStatus::Failed->value, $failedPayment->fresh()->status);
        $this->assertSame($failedPayment->failed_at, $failedPayment->fresh()->failed_at);
        $this->assertDatabaseCount('order_payments', 1);
        $this->assertHistory($order, OrderHistoryType::Payment->value, 'failed', 'cancelled');
        $this->assertHistory($order, OrderHistoryType::Order->value, 'pending', 'cancelled');
    }

    public function test_order_details_disables_processing_when_prepayment_is_missing(): void
    {
        $this->actingAs(User::factory()->create());
        [$order] = $this->orderWithInventory();
        $order->update(['requires_payment_before_processing' => true]);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Process Order')
            ->assertSee('Payment is required before this order can be processed.')
            ->assertSee('disabled', false);
    }

    public function test_order_details_warns_when_failed_delivery_order_was_paid(): void
    {
        $this->actingAs(User::factory()->create());
        [$order] = $this->orderWithInventory();
        $this->orderStatusService->process($order);
        $this->paymentStatusService->markPaid($order);
        $this->orderStatusService->markOutForDelivery($order);
        $this->orderStatusService->markDeliveryFailed($order);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Paid order requires refund.')
            ->assertSee('Refund processing is not implemented yet.');
    }

    public function test_paid_processing_order_cannot_be_cancelled(): void
    {
        [$order, $product] = $this->orderWithInventory(8, [2]);
        $this->orderStatusService->process($order);
        $this->paymentStatusService->markPaid($order);

        try {
            $this->orderStatusService->cancel($order);
            $this->fail('A paid order was allowed to be cancelled.');
        } catch (ValidationException) {
            $freshOrder = $order->fresh();
            $this->assertSame(OrderStatus::Processing->value, $freshOrder->status);
            $this->assertSame(PaymentStatus::Paid->value, $freshOrder->payment_status);
            $this->assertSame('6.0000', $product->inventory()->first()->quantity);
            $this->assertDatabaseCount('inventory_movements', 1);
            $this->assertCancellationHistoryCount($order, 0);
        }
    }

    public function test_fulfilled_order_with_pending_payment_cannot_be_cancelled(): void
    {
        [$order, $product] = $this->orderWithInventory(8, [2]);
        $this->orderStatusService->process($order);
        $this->orderStatusService->markOutForDelivery($order);
        $this->orderStatusService->fulfill($order);

        try {
            $this->orderStatusService->cancel($order);
            $this->fail('A fulfilled order was allowed to be cancelled.');
        } catch (ValidationException) {
            $freshOrder = $order->fresh();
            $this->assertSame(OrderStatus::Processing->value, $freshOrder->status);
            $this->assertSame(FulfillmentStatus::Fulfilled->value, $freshOrder->fulfillment_status);
            $this->assertSame('6.0000', $product->inventory()->first()->quantity);
            $this->assertDatabaseCount('inventory_movements', 1);
            $this->assertCancellationHistoryCount($order, 0);
        }
    }

    public function test_failed_payment_does_not_complete_a_fulfilled_order(): void
    {
        [$order] = $this->orderWithInventory();
        $this->orderStatusService->process($order);
        $this->orderStatusService->markOutForDelivery($order);
        $this->orderStatusService->fulfill($order);

        $failed = $this->paymentStatusService->markFailed($order);

        $this->assertSame(OrderStatus::Processing->value, $failed->status);
        $this->assertSame(PaymentStatus::Failed->value, $failed->payment_status);
        $this->assertSame(FulfillmentStatus::Fulfilled->value, $failed->fulfillment_status);
        $this->assertNull($failed->completed_at);
        $this->assertCompletionHistoryCount($order, 0);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'status' => PaymentStatus::Failed->value,
        ]);
        $this->assertNotNull($order->payments()->first()->failed_at);
    }

    public function test_failed_payment_retry_creates_a_new_pending_attempt_and_history(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);
        [$order] = $this->orderWithInventory();
        $order->payments()->firstOrFail()->update(['amount' => 17.25]);
        $this->paymentStatusService->markFailed($order);
        $failedPayment = $order->payments()->firstOrFail()->fresh();
        $failedUpdatedAt = $failedPayment->updated_at;
        $order->update(['payment_method' => 'online_card']);

        $retried = $this->paymentStatusService->retry($order);
        $newPayment = $order->payments()->latest('id')->firstOrFail();

        $this->assertSame(PaymentStatus::Pending->value, $retried->payment_status);
        $this->assertDatabaseCount('order_payments', 2);
        $this->assertSame(PaymentStatus::Failed->value, $failedPayment->fresh()->status);
        $this->assertEquals($failedUpdatedAt, $failedPayment->fresh()->updated_at);
        $this->assertNotNull($failedPayment->fresh()->failed_at);
        $this->assertSame('online_card', $newPayment->method);
        $this->assertEquals(17.25, $newPayment->amount);
        $this->assertSame(PaymentStatus::Pending->value, $newPayment->status);
        $this->assertNull($newPayment->transaction_reference);
        $this->assertNull($newPayment->failure_message);
        $this->assertNull($newPayment->paid_at);
        $this->assertNull($newPayment->failed_at);
        $this->assertSame(1, OrderStatusHistory::query()
            ->where('order_id', $order->id)
            ->where('type', OrderHistoryType::Payment->value)
            ->where('from_status', PaymentStatus::Failed->value)
            ->where('to_status', PaymentStatus::Pending->value)
            ->where('created_by', $admin->id)
            ->count());
    }

    public function test_retried_payment_completes_an_already_fulfilled_order_when_paid(): void
    {
        [$order] = $this->orderWithInventory();
        $this->orderStatusService->process($order);
        $this->orderStatusService->markOutForDelivery($order);
        $this->orderStatusService->fulfill($order);
        $this->paymentStatusService->markFailed($order);
        $this->paymentStatusService->retry($order);

        $completed = $this->paymentStatusService->markPaid($order);

        $this->assertCompletedOrder($completed);
        $this->assertDatabaseCount('order_payments', 2);
        $this->assertSame(
            [PaymentStatus::Failed->value, PaymentStatus::Paid->value],
            $order->payments()->orderBy('id')->pluck('status')->all()
        );
        $this->assertCompletionHistoryCount($order, 1);
    }

    public function test_multiple_failed_retries_preserve_every_attempt(): void
    {
        [$order] = $this->orderWithInventory();
        $this->paymentStatusService->markFailed($order);
        $firstFailed = $order->payments()->firstOrFail()->fresh();

        $this->paymentStatusService->retry($order);
        $this->paymentStatusService->markFailed($order);
        $secondFailed = $order->payments()->latest('id')->firstOrFail()->fresh();
        $this->paymentStatusService->retry($order);

        $this->assertSame(PaymentStatus::Pending->value, $order->fresh()->payment_status);
        $this->assertDatabaseCount('order_payments', 3);
        $this->assertSame(
            [
                PaymentStatus::Failed->value,
                PaymentStatus::Failed->value,
                PaymentStatus::Pending->value,
            ],
            $order->payments()->orderBy('id')->pluck('status')->all()
        );
        $this->assertSame($firstFailed->failed_at, $firstFailed->fresh()->failed_at);
        $this->assertSame($secondFailed->failed_at, $secondFailed->fresh()->failed_at);
        $this->assertSame(2, OrderStatusHistory::query()
            ->where('order_id', $order->id)
            ->where('type', OrderHistoryType::Payment->value)
            ->where('from_status', PaymentStatus::Failed->value)
            ->where('to_status', PaymentStatus::Pending->value)
            ->count());
    }

    public function test_payment_retry_is_rejected_while_payment_is_pending(): void
    {
        [$order] = $this->orderWithInventory();

        $this->assertRetryIsRejected(
            $order,
            'payment_status',
            'Only failed payments can be retried.'
        );
    }

    public function test_payment_retry_is_rejected_after_payment_is_paid(): void
    {
        [$order] = $this->orderWithInventory();
        $this->paymentStatusService->markPaid($order);

        $this->assertRetryIsRejected(
            $order,
            'payment_status',
            'Only failed payments can be retried.'
        );
    }

    public function test_payment_retry_is_rejected_for_a_cancelled_order(): void
    {
        [$order] = $this->orderWithInventory();
        $this->paymentStatusService->markFailed($order);
        $this->orderStatusService->cancel($order);

        $this->assertRetryIsRejected(
            $order,
            'status',
            'Payments cannot be updated for cancelled or completed orders.'
        );
    }

    public function test_payment_retry_is_rejected_for_a_completed_order(): void
    {
        [$order] = $this->orderWithInventory();
        $this->paymentStatusService->markFailed($order);
        $order->update(['status' => OrderStatus::Completed->value]);

        $this->assertRetryIsRejected(
            $order,
            'status',
            'Payments cannot be updated for cancelled or completed orders.'
        );
    }

    public function test_order_details_shows_retry_only_for_eligible_failed_payment(): void
    {
        $this->actingAs(User::factory()->create());
        [$order] = $this->orderWithInventory();
        $this->paymentStatusService->markFailed($order);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Retry Payment')
            ->assertDontSee('Mark Paid')
            ->assertDontSee('Mark Failed');
    }

    public function test_cancelled_order_cannot_be_marked_paid(): void
    {
        [$order] = $this->orderWithInventory();
        $this->orderStatusService->cancel($order);

        $this->assertPaymentTransitionIsRejected(
            $order,
            fn () => $this->paymentStatusService->markPaid($order)
        );
    }

    public function test_cancelled_order_cannot_be_marked_failed(): void
    {
        [$order] = $this->orderWithInventory();
        $this->orderStatusService->cancel($order);

        $this->assertPaymentTransitionIsRejected(
            $order,
            fn () => $this->paymentStatusService->markFailed($order)
        );
    }

    public function test_completed_order_cannot_be_marked_paid(): void
    {
        [$order] = $this->orderWithInventory();
        $order->update(['status' => OrderStatus::Completed->value]);

        $this->assertPaymentTransitionIsRejected(
            $order,
            fn () => $this->paymentStatusService->markPaid($order)
        );
    }

    public function test_completed_order_cannot_be_marked_failed(): void
    {
        [$order] = $this->orderWithInventory();
        $order->update(['status' => OrderStatus::Completed->value]);

        $this->assertPaymentTransitionIsRejected(
            $order,
            fn () => $this->paymentStatusService->markFailed($order)
        );
    }

    public function test_lifecycle_records_preserve_the_authenticated_admin_id(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);
        [$order] = $this->orderWithInventory();

        $this->orderStatusService->process($order);
        $this->paymentStatusService->markPaid($order);
        $this->orderStatusService->markOutForDelivery($order);
        $this->orderStatusService->fulfill($order);

        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseMissing('inventory_movements', ['created_by' => null]);
        $this->assertDatabaseHas('inventory_movements', ['created_by' => $admin->id]);
        $this->assertDatabaseCount('order_status_history', 5);
        $this->assertSame(
            [$admin->id],
            OrderStatusHistory::query()->distinct()->pluck('created_by')->all()
        );
    }

    public function test_completion_service_rejects_calls_outside_a_transaction(): void
    {
        $order = new Order([
            'status' => OrderStatus::Processing->value,
            'payment_status' => PaymentStatus::Paid->value,
            'fulfillment_status' => FulfillmentStatus::Fulfilled->value,
        ]);
        $transactionLevel = DB::transactionLevel();

        for ($level = 0; $level < $transactionLevel; $level++) {
            DB::rollBack();
        }

        try {
            $this->orderCompletionService->completeIfEligible($order);
            $this->fail('Completion outside a transaction was not rejected.');
        } catch (LogicException) {
            $this->assertSame(OrderStatus::Processing->value, $order->status);
            $this->assertNull($order->completed_at);
            $this->assertDatabaseCount('order_status_history', 0);
        } finally {
            for ($level = 0; $level < $transactionLevel; $level++) {
                DB::beginTransaction();
            }
        }
    }

    private function orderWithInventory(float $stock = 10, array $itemQuantities = [2]): array
    {
        $product = Product::create([
            'type' => 'simple',
            'sku' => 'SKU-'.uniqid(),
            'price' => 10,
            'is_new' => false,
            'is_featured' => false,
            'is_visible_individually' => true,
            'status' => true,
        ]);

        $product->inventory()->create([
            'quantity' => $stock,
            'average_cost' => 4,
            'low_stock_alert' => null,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-'.str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
            'customer_email' => 'customer@example.com',
            'customer_first_name' => 'Test',
            'customer_last_name' => 'Customer',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => OrderStatus::Pending->value,
            'payment_status' => PaymentStatus::Pending->value,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled->value,
            'payment_method' => 'cash',
            'subtotal' => 20,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 20,
            'placed_at' => now(),
        ]);

        foreach ($itemQuantities as $index => $quantity) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_type' => 'simple',
                'sku' => $product->sku,
                'name' => 'Snapshot Product '.($index + 1),
                'quantity' => $quantity,
                'original_unit_price' => 10,
                'unit_price' => 10,
                'tax_amount' => 0,
                'row_subtotal' => $quantity * 10,
                'row_total' => $quantity * 10,
                'unit_cost' => null,
                'is_inventory_item' => true,
            ]);
        }

        OrderPayment::create([
            'order_id' => $order->id,
            'method' => 'cash',
            'status' => PaymentStatus::Pending->value,
            'amount' => $order->grand_total,
        ]);

        return [$order, $product];
    }

    private function assertCompletedOrder(Order $order): void
    {
        $this->assertSame(OrderStatus::Completed->value, $order->status);
        $this->assertSame(PaymentStatus::Paid->value, $order->payment_status);
        $this->assertSame(FulfillmentStatus::Fulfilled->value, $order->fulfillment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->completed_at);
        $this->assertNotNull($order->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->firstOrFail()
            ->paid_at);
    }

    private function assertHistory(Order $order, string $type, string $from, string $to): void
    {
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
        ]);
    }

    private function assertCompletionHistoryCount(Order $order, int $count): void
    {
        $this->assertSame($count, OrderStatusHistory::query()
            ->where('order_id', $order->id)
            ->where('type', OrderHistoryType::Order->value)
            ->where('from_status', OrderStatus::Processing->value)
            ->where('to_status', OrderStatus::Completed->value)
            ->count());
    }

    private function assertCancellationHistoryCount(Order $order, int $count): void
    {
        $this->assertSame($count, OrderStatusHistory::query()
            ->where('order_id', $order->id)
            ->where('type', OrderHistoryType::Order->value)
            ->where('to_status', OrderStatus::Cancelled->value)
            ->count());
    }

    private function assertPaymentTransitionIsRejected(Order $order, callable $transition): void
    {
        $status = $order->fresh()->status;
        $orderPaymentStatus = $order->fresh()->payment_status;
        $payment = $order->payments()->firstOrFail();
        $paymentStatus = $payment->status;
        $paidAt = $payment->paid_at;
        $failedAt = $payment->failed_at;
        $historyCount = $order->statusHistory()->count();

        try {
            $transition();
            $this->fail('A terminal order was allowed to change payment status.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Payments cannot be updated for cancelled or completed orders.'],
                $exception->errors()['status']
            );
            $this->assertSame($status, $order->fresh()->status);
            $this->assertSame($orderPaymentStatus, $order->fresh()->payment_status);
            $this->assertSame($paymentStatus, $payment->fresh()->status);
            $this->assertSame($paidAt, $payment->fresh()->paid_at);
            $this->assertSame($failedAt, $payment->fresh()->failed_at);
            $this->assertSame($historyCount, $order->statusHistory()->count());
            $this->assertCompletionHistoryCount($order, 0);
        }
    }

    private function assertRetryIsRejected(
        Order $order,
        string $errorKey,
        string $message
    ): void {
        $orderStatus = $order->fresh()->status;
        $paymentStatus = $order->fresh()->payment_status;
        $paymentCount = $order->payments()->count();
        $historyCount = $order->statusHistory()->count();
        $paymentSnapshots = $order->payments()
            ->orderBy('id')
            ->get(['id', 'status', 'paid_at', 'failed_at', 'updated_at'])
            ->toArray();

        try {
            $this->paymentStatusService->retry($order);
            $this->fail('An ineligible payment retry was allowed.');
        } catch (ValidationException $exception) {
            $this->assertSame([$message], $exception->errors()[$errorKey]);
            $this->assertSame($orderStatus, $order->fresh()->status);
            $this->assertSame($paymentStatus, $order->fresh()->payment_status);
            $this->assertSame($paymentCount, $order->payments()->count());
            $this->assertSame($historyCount, $order->statusHistory()->count());
            $this->assertSame(
                $paymentSnapshots,
                $order->payments()
                    ->orderBy('id')
                    ->get(['id', 'status', 'paid_at', 'failed_at', 'updated_at'])
                    ->toArray()
            );
        }
    }
}
