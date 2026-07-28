<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderHistoryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    private const MAX_ORDER_NUMBER_ATTEMPTS = 3;

    public function __construct(
        private OrderNumberGenerator $orderNumberGenerator,
        private PaymentNumberGenerator $paymentNumberGenerator
    ) {}

    public function create(array $data): Order
    {
        for ($attempt = 1; $attempt <= self::MAX_ORDER_NUMBER_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use ($data) {
                    $paymentMethod = $this->paymentMethod($data['payment_method'] ?? null);
                    $order = $this->createOrder(
                        $data,
                        $this->orderNumberGenerator->generate(),
                        $paymentMethod
                    );

                    $this->createItems($order, $data['items']);
                    $this->createAddresses($order, $data);
                    $this->createPayment($order, $paymentMethod);
                    $this->createInitialHistory($order);

                    return $order;
                });
            } catch (QueryException $exception) {
                if (! $this->isOrderNumberUniqueViolation($exception)
                    || $attempt === self::MAX_ORDER_NUMBER_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Order creation retry loop ended unexpectedly.');
    }

    private function createOrder(
        array $data,
        string $orderNumber,
        PaymentMethod $paymentMethod
    ): Order {
        $orderData = $data;
        unset(
            $orderData['items'],
            $orderData['billing_address'],
            $orderData['shipping_address'],
            $orderData['payment']
        );

        return Order::create(array_merge($orderData, [
            'order_number' => $orderNumber,
            'payment_method' => $paymentMethod->code,
            'requires_payment_before_processing' => $paymentMethod->requires_payment_before_processing,
            'status' => OrderStatus::Pending->value,
            'payment_status' => PaymentStatus::Pending->value,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled->value,
        ]));
    }

    private function createItems(Order $order, array $items): void
    {
        foreach ($items as $item) {
            $children = $item['children'] ?? [];
            unset($item['children']);
            $item['unit_cost'] = null;

            $parent = $order->items()->create($item);

            foreach ($children as $child) {
                $this->createChildItem($order, $parent, $child);
            }
        }
    }

    private function createChildItem(Order $order, OrderItem $parent, array $child): void
    {
        $order->items()->create(array_merge($child, [
            'parent_order_item_id' => $parent->id,
            'unit_cost' => null,
        ]));
    }

    private function createAddresses(Order $order, array $data): void
    {
        $order->addresses()->create(array_merge($data['billing_address'], [
            'type' => 'billing',
        ]));

        $order->addresses()->create(array_merge($data['shipping_address'], [
            'type' => 'shipping',
        ]));
    }

    private function createPayment(
        Order $order,
        PaymentMethod $paymentMethod
    ): void {
        $order->payment()->create([
            'payment_number' => $this->paymentNumberGenerator->generate(),
            'payment_method_id' => $paymentMethod->getKey(),
            'method_code' => $paymentMethod->code,
            'method_name' => $paymentMethod->name,
            'method_type' => $paymentMethod->type->value,
            'amount' => $order->grand_total,
            'currency_code' => $order->currency_code,
            'status' => PaymentStatus::Pending,
            'paid_amount' => '0.0000',
            'paid_at' => null,
        ]);
    }

    private function paymentMethod(mixed $code): PaymentMethod
    {
        if (! is_string($code) || $code === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'A valid active payment method is required.',
            ]);
        }

        $paymentMethod = PaymentMethod::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method' => 'The selected payment method is unavailable.',
            ]);
        }

        return $paymentMethod;
    }

    private function createInitialHistory(Order $order): void
    {
        $order->statusHistory()->create([
            'type' => OrderHistoryType::Order->value,
            'from_status' => null,
            'to_status' => OrderStatus::Pending->value,
            'comment' => null,
            'created_by' => auth()->id(),
        ]);
    }

    private function isOrderNumberUniqueViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && (str_contains($message, 'orders_order_number_unique')
                || str_contains($message, 'orders.order_number'));
    }
}
