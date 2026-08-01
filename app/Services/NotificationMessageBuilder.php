<?php

namespace App\Services;

use App\DTOs\Notifications\NotificationDispatchDecision;
use App\Enums\NotificationEventCode;
use App\Models\Order;
use App\Models\OrderCancellationRequest;
use App\Models\OrderPayment;

class NotificationMessageBuilder
{
    private const ORDER_EVENTS = [
        NotificationEventCode::OrderPlaced->value,
        NotificationEventCode::OrderCompleted->value,
        NotificationEventCode::OrderCancelled->value,
        NotificationEventCode::DeliveryFailed->value,
    ];

    private const PAYMENT_EVENTS = [
        NotificationEventCode::PaymentPaid->value,
        NotificationEventCode::PaymentFailed->value,
        NotificationEventCode::PaymentCancelled->value,
    ];

    private const CANCELLATION_EVENTS = [
        NotificationEventCode::CancellationRequestSubmitted->value,
        NotificationEventCode::CancellationRequestApproved->value,
        NotificationEventCode::CancellationRequestRejected->value,
    ];

    public function build(NotificationDispatchDecision $decision, string $locale): ?array
    {
        $context = $this->resolveContext($decision);

        if ($context === null) {
            return null;
        }

        return $this->buildFromContext($decision, $context, $locale);
    }

    public function buildFromContext(
        NotificationDispatchDecision $decision,
        array $context,
        string $locale
    ): array {
        $key = 'shop.notifications.events.'.$decision->event;
        $replacements = [
            'order_number' => $context['order_number'],
            'payment_number' => $context['payment_number'] ?? '',
        ];

        return [
            'title' => __($key.'.title', $replacements, $locale),
            'body' => __($key.'.body', $replacements, $locale),
            'payload' => $context['payload'],
            'customer_id' => $context['customer_id'],
            'customer_locale' => $context['customer_locale'],
        ];
    }

    public function resolveContext(NotificationDispatchDecision $decision): ?array
    {
        if ($decision->entityType === 'order' && in_array($decision->event, self::ORDER_EVENTS, true)) {
            $order = Order::query()->find($decision->entityId, ['id', 'user_id', 'order_number', 'locale']);

            return $order ? $this->orderContext($order) : null;
        }

        if ($decision->entityType === 'order_payment' && in_array($decision->event, self::PAYMENT_EVENTS, true)) {
            $payment = OrderPayment::query()
                ->with('order:id,user_id,order_number,locale')
                ->find($decision->entityId, ['id', 'order_id', 'payment_number']);

            if (! $payment?->order) {
                return null;
            }

            return $this->orderContext($payment->order, [
                'payment_id' => $payment->getKey(),
                'payment_number' => $payment->payment_number,
            ]);
        }

        if ($decision->entityType === 'order_cancellation_request'
            && in_array($decision->event, self::CANCELLATION_EVENTS, true)) {
            $request = OrderCancellationRequest::query()
                ->with('order:id,user_id,order_number,locale')
                ->find($decision->entityId, ['id', 'order_id']);

            if (! $request?->order) {
                return null;
            }

            return $this->orderContext($request->order, [
                'cancellation_request_id' => $request->getKey(),
            ]);
        }

        return null;
    }

    private function orderContext(Order $order, array $extraPayload = []): array
    {
        return [
            'order_number' => $order->order_number,
            'payment_number' => $extraPayload['payment_number'] ?? null,
            'customer_id' => $order->user_id ? (int) $order->user_id : null,
            'customer_locale' => $order->locale,
            'payload' => array_merge([
                'order_id' => (int) $order->getKey(),
                'order_number' => $order->order_number,
            ], $extraPayload),
        ];
    }
}
