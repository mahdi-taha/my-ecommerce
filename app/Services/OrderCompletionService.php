<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\NotificationEventCode;
use App\Enums\OrderHistoryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\CommerceEventOccurred;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class OrderCompletionService
{
    public function completeIfEligible(Order $order, ?int $userId = null): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Automatic order completion requires an active database transaction.');
        }

        if ($order->status !== OrderStatus::Processing->value
            || $order->payment_status !== PaymentStatus::Paid->value
            || $order->fulfillment_status !== FulfillmentStatus::Fulfilled->value) {
            return;
        }

        $completedAt = now();

        if (! $order->update([
            'status' => OrderStatus::Completed->value,
            'completed_at' => $completedAt,
        ])) {
            throw new RuntimeException('The order could not be completed.');
        }

        OrderStatusHistory::create([
            'order_id' => $order->getKey(),
            'type' => OrderHistoryType::Order->value,
            'from_status' => OrderStatus::Processing->value,
            'to_status' => OrderStatus::Completed->value,
            'created_by' => $userId,
            'comment' => null,
        ]);

        CommerceEventOccurred::dispatch(
            NotificationEventCode::OrderCompleted,
            'order',
            (int) $order->getKey()
        );
    }
}
