<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\NotificationEventCode;
use App\Enums\OrderCancellationRequestStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\CommerceEventOccurred;
use App\Models\Order;
use App\Models\OrderCancellationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCancellationRequestService
{
    public function __construct(private OrderStatusService $orderStatusService) {}

    public function create(Order $order, User $customer, string $reason): OrderCancellationRequest
    {
        return DB::transaction(function () use ($order, $customer, $reason) {
            $lockedOrder = $this->lockOrder($order);

            if ((int) $lockedOrder->user_id !== (int) $customer->getKey()) {
                abort(404);
            }

            $this->ensureRequestable($lockedOrder);

            if ($this->hasPendingRequest($lockedOrder)) {
                throw ValidationException::withMessages([
                    'reason' => __('shop.account.orders.cancellation.already_pending'),
                ]);
            }

            $cancellationRequest = OrderCancellationRequest::query()->create([
                'order_id' => $lockedOrder->getKey(),
                'user_id' => $customer->getKey(),
                'reason' => trim($reason),
                'status' => OrderCancellationRequestStatus::Pending,
                'pending_marker' => true,
            ]);

            CommerceEventOccurred::dispatch(
                NotificationEventCode::CancellationRequestSubmitted,
                'order_cancellation_request',
                (int) $cancellationRequest->getKey()
            );

            return $cancellationRequest;
        });
    }

    public function approve(
        Order $order,
        OrderCancellationRequest $cancellationRequest,
        User $administrator
    ): OrderCancellationRequest {
        return DB::transaction(function () use ($order, $cancellationRequest, $administrator) {
            $lockedOrder = $this->lockOrder($order);
            $lockedRequest = $this->lockRequest($lockedOrder, $cancellationRequest);
            $this->ensurePending($lockedRequest);

            $this->orderStatusService->cancel($lockedOrder);

            $timestamp = now();
            $lockedRequest->update([
                'status' => OrderCancellationRequestStatus::Approved,
                'pending_marker' => null,
                'reviewed_by' => $administrator->getKey(),
                'reviewed_at' => $timestamp,
            ]);

            CommerceEventOccurred::dispatch(
                NotificationEventCode::CancellationRequestApproved,
                'order_cancellation_request',
                (int) $lockedRequest->getKey()
            );

            return $lockedRequest->fresh(['requester', 'reviewer']);
        });
    }

    public function reject(
        Order $order,
        OrderCancellationRequest $cancellationRequest,
        User $administrator,
        string $adminNote
    ): OrderCancellationRequest {
        return DB::transaction(function () use ($order, $cancellationRequest, $administrator, $adminNote) {
            $lockedOrder = $this->lockOrder($order);
            $lockedRequest = $this->lockRequest($lockedOrder, $cancellationRequest);
            $this->ensurePending($lockedRequest);

            $lockedRequest->update([
                'status' => OrderCancellationRequestStatus::Rejected,
                'pending_marker' => null,
                'admin_note' => trim($adminNote),
                'reviewed_by' => $administrator->getKey(),
                'reviewed_at' => now(),
            ]);

            CommerceEventOccurred::dispatch(
                NotificationEventCode::CancellationRequestRejected,
                'order_cancellation_request',
                (int) $lockedRequest->getKey()
            );

            return $lockedRequest->fresh(['requester', 'reviewer']);
        });
    }

    public function canRequest(Order $order): bool
    {
        return $this->isLifecycleRequestable($order) && ! $this->hasPendingRequest($order);
    }

    public function cancelDirectly(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = $this->lockOrder($order);

            if ($this->hasPendingRequest($lockedOrder)) {
                throw ValidationException::withMessages([
                    'request' => 'Review the pending customer cancellation request before cancelling this Order.',
                ]);
            }

            return $this->orderStatusService->cancel($lockedOrder);
        });
    }

    private function ensureRequestable(Order $order): void
    {
        if (! $this->isLifecycleRequestable($order)) {
            throw ValidationException::withMessages([
                'reason' => __('shop.account.orders.cancellation.not_eligible'),
            ]);
        }
    }

    private function isLifecycleRequestable(Order $order): bool
    {
        return in_array($order->status, [
            OrderStatus::Pending->value,
            OrderStatus::Processing->value,
        ], true)
            && $order->fulfillment_status === FulfillmentStatus::Unfulfilled->value
            && $order->payment_status !== PaymentStatus::Paid->value;
    }

    private function hasPendingRequest(Order $order): bool
    {
        if ($order->relationLoaded('cancellationRequests')) {
            return $order->cancellationRequests->contains(
                fn (OrderCancellationRequest $request) => $request->status === OrderCancellationRequestStatus::Pending
            );
        }

        return $order->cancellationRequests()
            ->where('status', OrderCancellationRequestStatus::Pending->value)
            ->exists();
    }

    private function lockOrder(Order $order): Order
    {
        $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();

        abort_unless($lockedOrder, 404);

        return $lockedOrder;
    }

    private function lockRequest(
        Order $order,
        OrderCancellationRequest $cancellationRequest
    ): OrderCancellationRequest {
        $lockedRequest = OrderCancellationRequest::query()
            ->whereKey($cancellationRequest->getKey())
            ->where('order_id', $order->getKey())
            ->lockForUpdate()
            ->first();

        abort_unless($lockedRequest, 404);

        return $lockedRequest;
    }

    private function ensurePending(OrderCancellationRequest $request): void
    {
        if ($request->status !== OrderCancellationRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'request' => 'This cancellation request has already been reviewed.',
            ]);
        }
    }
}
