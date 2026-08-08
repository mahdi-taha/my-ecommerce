<?php

namespace App\Services;

use App\Enums\NotificationEventCode;
use App\Enums\OrderHistoryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Events\CommerceEventOccurred;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

class PaymentStatusService
{
    public function __construct(private OrderCompletionService $orderCompletionService) {}

    public function markPaid(Order $order): Order
    {
        return $this->transitionPendingPayment(
            $order,
            PaymentStatus::Paid,
            PaymentAttemptStatus::Paid
        );
    }

    public function markFailed(Order $order): Order
    {
        return $this->transitionPendingPayment(
            $order,
            PaymentStatus::Failed,
            PaymentAttemptStatus::Failed
        );
    }

    public function retry(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = $this->lockedOrder($order);
            $this->ensureOrderAcceptsPaymentUpdates($lockedOrder);

            if ($lockedOrder->payment_status !== PaymentStatus::Failed->value) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Only failed payments can be retried.',
                ]);
            }

            $payment = $this->lockedPayment($lockedOrder);

            if ($payment->status !== PaymentStatus::Failed) {
                throw new RuntimeException('The payment obligation does not match the Order payment status.');
            }

            $failedAttempt = $payment->attempts()
                ->where('status', PaymentAttemptStatus::Failed->value)
                ->latest('attempt_number')
                ->lockForUpdate()
                ->first();

            if (! $failedAttempt) {
                throw new RuntimeException('The order does not have a failed payment attempt to retry.');
            }

            $userId = auth()->id();
            $timestamp = now();
            $this->createAttempt($payment, PaymentAttemptStatus::Pending, $timestamp);

            if (! $payment->update([
                'status' => PaymentStatus::Pending,
                'paid_amount' => '0.0000',
                'paid_at' => null,
            ])) {
                throw new RuntimeException('The payment obligation could not be reset for retry.');
            }

            if (! $lockedOrder->update([
                'payment_status' => PaymentStatus::Pending->value,
                'paid_at' => null,
            ])) {
                throw new RuntimeException('The order payment status could not be reset for retry.');
            }

            $this->createHistory(
                $lockedOrder,
                PaymentStatus::Failed,
                PaymentStatus::Pending,
                $userId
            );

            return $lockedOrder->fresh();
        });
    }

    public function recordRefund(
        Order $order,
        OrderPayment $payment,
        Refund $refund,
        PaymentStatus $targetStatus,
        int $userId,
    ): void {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Refund payment transitions require an active database transaction.');
        }
        if ((int) $payment->order_id !== (int) $order->getKey()
            || (int) $refund->order_id !== (int) $order->getKey()
            || (int) $refund->order_payment_id !== (int) $payment->getKey()) {
            throw new RuntimeException('The Refund payment aggregate is inconsistent.');
        }

        $fromStatus = PaymentStatus::tryFrom($order->payment_status);
        if (! in_array($fromStatus, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)
            || $payment->status !== $fromStatus) {
            throw ValidationException::withMessages([
                'payment_status' => 'Only paid or partially refunded Payments can be refunded.',
            ]);
        }
        if (! in_array($targetStatus, [PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded], true)) {
            throw new LogicException('Invalid Refund payment target status.');
        }

        if (! $payment->update(['status' => $targetStatus])) {
            throw new RuntimeException('The Payment Refund status could not be updated.');
        }
        if (! $order->update(['payment_status' => $targetStatus->value])) {
            throw new RuntimeException('The Order Payment Refund status could not be updated.');
        }

        if ($fromStatus !== $targetStatus) {
            $this->createHistory($order, $fromStatus, $targetStatus, $userId, $refund->refund_number);
        }
    }

    private function transitionPendingPayment(
        Order $order,
        PaymentStatus $targetStatus,
        PaymentAttemptStatus $attemptStatus
    ): Order {
        return DB::transaction(function () use ($order, $targetStatus, $attemptStatus) {
            $lockedOrder = $this->lockedOrder($order);
            $this->ensureOrderAcceptsPaymentUpdates($lockedOrder);

            if ($lockedOrder->payment_status !== PaymentStatus::Pending->value) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Only pending payments can change payment status.',
                ]);
            }

            $payment = $this->lockedPayment($lockedOrder);

            if ($payment->status !== PaymentStatus::Pending) {
                throw new RuntimeException('The payment obligation does not match the Order payment status.');
            }

            $userId = auth()->id();
            $timestamp = now();
            $attempt = $this->lockedNonterminalAttempt($payment)
                ?? $this->createAttempt($payment, PaymentAttemptStatus::Pending, $timestamp);

            if (! $attempt->update([
                'status' => $attemptStatus,
                'completed_at' => $timestamp,
            ])) {
                throw new RuntimeException('The payment attempt could not be completed.');
            }

            $paymentUpdates = [
                'status' => $targetStatus,
                'paid_amount' => $targetStatus === PaymentStatus::Paid
                    ? $payment->amount
                    : '0.0000',
                'paid_at' => $targetStatus === PaymentStatus::Paid ? $timestamp : null,
            ];

            if (! $payment->update($paymentUpdates)) {
                throw new RuntimeException('The payment obligation could not be updated.');
            }

            if (! $lockedOrder->update([
                'payment_status' => $targetStatus->value,
                'paid_at' => $targetStatus === PaymentStatus::Paid ? $timestamp : null,
            ])) {
                throw new RuntimeException('The order payment status could not be updated.');
            }

            $this->createHistory(
                $lockedOrder,
                PaymentStatus::Pending,
                $targetStatus,
                $userId
            );

            CommerceEventOccurred::dispatch(
                $targetStatus === PaymentStatus::Paid
                    ? NotificationEventCode::PaymentPaid
                    : NotificationEventCode::PaymentFailed,
                'order_payment',
                (int) $payment->getKey()
            );

            if ($targetStatus === PaymentStatus::Paid) {
                $this->orderCompletionService->completeIfEligible($lockedOrder, $userId);
            }

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

    private function lockedPayment(Order $order): OrderPayment
    {
        $payment = OrderPayment::query()
            ->where('order_id', $order->getKey())
            ->lockForUpdate()
            ->first();

        if (! $payment) {
            throw new RuntimeException('The order does not have a payment obligation.');
        }

        return $payment;
    }

    private function ensureOrderAcceptsPaymentUpdates(Order $order): void
    {
        if (in_array($order->status, [
            OrderStatus::Cancelled->value,
            OrderStatus::Completed->value,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Payments cannot be updated for cancelled or completed orders.',
            ]);
        }
    }

    private function lockedNonterminalAttempt(OrderPayment $payment): ?PaymentAttempt
    {
        return $payment->attempts()
            ->whereIn('status', [
                PaymentAttemptStatus::Pending->value,
                PaymentAttemptStatus::RequiresAction->value,
                PaymentAttemptStatus::Processing->value,
            ])
            ->latest('attempt_number')
            ->lockForUpdate()
            ->first();
    }

    private function createAttempt(
        OrderPayment $payment,
        PaymentAttemptStatus $status,
        mixed $timestamp
    ): PaymentAttempt {
        $lastAttemptNumber = (int) $payment->attempts()->max('attempt_number');

        return $payment->attempts()->create([
            'attempt_number' => $lastAttemptNumber + 1,
            'provider' => null,
            'status' => $status,
            'amount' => $payment->amount,
            'currency_code' => $payment->currency_code,
            'transaction_reference' => null,
            'customer_note' => null,
            'provider_transaction_id' => null,
            'failure_code' => null,
            'failure_message' => null,
            'metadata_json' => null,
            'initiated_at' => $timestamp,
            'completed_at' => null,
        ]);
    }

    private function createHistory(
        Order $order,
        PaymentStatus $fromStatus,
        PaymentStatus $toStatus,
        ?int $userId,
        ?string $comment = null,
    ): void {
        OrderStatusHistory::create([
            'order_id' => $order->getKey(),
            'type' => OrderHistoryType::Payment->value,
            'from_status' => $fromStatus->value,
            'to_status' => $toStatus->value,
            'created_by' => $userId,
            'comment' => $comment,
        ]);
    }
}
