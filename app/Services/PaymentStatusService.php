<?php

namespace App\Services;

use App\Enums\OrderHistoryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentStatusService
{
    public function __construct(private OrderCompletionService $orderCompletionService) {}

    public function markPaid(Order $order): Order
    {
        $paidAt = now();

        return $this->transitionPaymentStatus(
            $order,
            PaymentStatus::Paid,
            ['paid_at' => $paidAt],
            ['paid_at' => $paidAt]
        );
    }

    public function markFailed(Order $order): Order
    {
        return $this->transitionPaymentStatus(
            $order,
            PaymentStatus::Failed,
            [],
            ['failed_at' => now()]
        );
    }

    public function retry(Order $order): Order
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

            if (in_array($lockedOrder->status, [
                OrderStatus::Cancelled->value,
                OrderStatus::Completed->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Payments cannot be updated for cancelled or completed orders.',
                ]);
            }

            if ($lockedOrder->payment_status !== PaymentStatus::Failed->value) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Only failed payments can be retried.',
                ]);
            }

            $failedPayment = OrderPayment::query()
                ->where('order_id', $lockedOrder->getKey())
                ->where('status', PaymentStatus::Failed->value)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $failedPayment) {
                throw new RuntimeException('The order does not have a failed payment attempt to retry.');
            }

            $payment = OrderPayment::create([
                'order_id' => $lockedOrder->getKey(),
                'method' => $lockedOrder->payment_method,
                'status' => PaymentStatus::Pending->value,
                'amount' => $failedPayment->amount,
                'transaction_reference' => null,
                'failure_message' => null,
                'paid_at' => null,
                'failed_at' => null,
            ]);

            if (! $payment->exists) {
                throw new RuntimeException('The payment retry could not be created.');
            }

            $userId = auth()->id();

            if (! $lockedOrder->update([
                'payment_status' => PaymentStatus::Pending->value,
            ])) {
                throw new RuntimeException('The order payment status could not be reset for retry.');
            }

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->getKey(),
                'type' => OrderHistoryType::Payment->value,
                'from_status' => PaymentStatus::Failed->value,
                'to_status' => PaymentStatus::Pending->value,
                'created_by' => $userId,
                'comment' => null,
            ]);

            return $lockedOrder->fresh();
        });
    }

    private function transitionPaymentStatus(
        Order $order,
        PaymentStatus $targetStatus,
        array $orderUpdates,
        array $paymentUpdates
    ): Order {
        return DB::transaction(function () use ($order, $targetStatus, $orderUpdates, $paymentUpdates) {
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

            if (in_array($lockedOrder->status, [
                OrderStatus::Cancelled->value,
                OrderStatus::Completed->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Payments cannot be updated for cancelled or completed orders.',
                ]);
            }

            if ($lockedOrder->payment_status !== PaymentStatus::Pending->value) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Only pending payments can change payment status.',
                ]);
            }

            $payment = OrderPayment::query()
                ->where('order_id', $lockedOrder->getKey())
                ->where('status', PaymentStatus::Pending->value)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new RuntimeException('The order does not have a pending payment record.');
            }

            $userId = auth()->id();

            if (! $lockedOrder->update(array_merge($orderUpdates, [
                'payment_status' => $targetStatus->value,
            ]))) {
                throw new RuntimeException('The order payment status could not be updated.');
            }

            if (! $payment->update(array_merge($paymentUpdates, [
                'status' => $targetStatus->value,
            ]))) {
                throw new RuntimeException('The payment record could not be updated.');
            }

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->getKey(),
                'type' => OrderHistoryType::Payment->value,
                'from_status' => PaymentStatus::Pending->value,
                'to_status' => $targetStatus->value,
                'created_by' => $userId,
                'comment' => null,
            ]);

            if ($targetStatus === PaymentStatus::Paid) {
                $this->orderCompletionService->completeIfEligible($lockedOrder, $userId);
            }

            return $lockedOrder->fresh();
        });
    }
}
