<?php

namespace App\Services;

use App\Enums\NotificationEventCode;
use App\Enums\PaymentStatus;
use App\Enums\ShippingTreatment;
use App\Events\CommerceEventOccurred;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundService
{
    private const MAX_DECIMAL = '99999999999.9999';

    public function __construct(
        private RefundNumberGenerator $numbers,
        private PaymentStatusService $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Order $order, User $administrator, array $data, string $idempotencyKey): Refund
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The Refund submission token is invalid.',
            ]);
        }
        if ($existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($order, $administrator, $data, $idempotencyKey): Refund {
                $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();
                if (! $lockedOrder) {
                    throw ValidationException::withMessages(['order' => 'The Order no longer exists.']);
                }
                $lockedAdmin = User::query()->admins()->active()
                    ->whereKey($administrator->getKey())->lockForUpdate()->first();
                if (! $lockedAdmin) {
                    throw ValidationException::withMessages(['administrator' => 'An active Administrator is required.']);
                }
                if ($existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first()) {
                    return $existing;
                }

                $payment = OrderPayment::query()
                    ->where('order_id', $lockedOrder->getKey())
                    ->lockForUpdate()
                    ->first();
                if (! $payment) {
                    throw ValidationException::withMessages(['payment' => 'The Order has no Payment obligation.']);
                }
                if ($payment->currency_code !== $lockedOrder->currency_code
                    || $payment->status->value !== $lockedOrder->payment_status
                    || ! in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
                    throw ValidationException::withMessages([
                        'payment_status' => 'The Order Payment is not eligible for a Refund.',
                    ]);
                }

                $selectedIds = collect($data['items'] ?? [])
                    ->pluck('order_item_id')->map(fn ($id): int => (int) $id)->sort()->values();
                OrderItem::query()->where('order_id', $lockedOrder->getKey())
                    ->whereIn('id', $selectedIds)->orderBy('id')->lockForUpdate()->get();
                Refund::query()->where('order_id', $lockedOrder->getKey())
                    ->orderBy('id')->lockForUpdate()->get(['id']);
                RefundItem::query()->whereHas('refund', fn ($query) => $query
                    ->where('order_id', $lockedOrder->getKey()))
                    ->orderBy('order_item_id')->orderBy('id')->lockForUpdate()->get(['id']);

                $quote = $this->quote($lockedOrder, $data);
                $previousCustomerAmount = Refund::query()
                    ->where('order_id', $lockedOrder->getKey())
                    ->sum('customer_refund_amount');
                if ($this->compare(
                    $this->add($previousCustomerAmount, $quote['customer_refund_amount']),
                    $payment->paid_amount
                ) > 0) {
                    throw ValidationException::withMessages([
                        'refund' => 'The cumulative Customer Refund exceeds the original paid amount.',
                    ]);
                }

                $timestamp = now();
                $refund = Refund::query()->create([
                    'refund_number' => $this->numbers->generate(),
                    'idempotency_key' => $idempotencyKey,
                    'order_id' => $lockedOrder->getKey(),
                    'order_payment_id' => $payment->getKey(),
                    'currency_code' => $lockedOrder->currency_code,
                    'merchandise_subtotal' => $quote['merchandise_subtotal'],
                    'discount_amount' => $quote['discount_amount'],
                    'tax_amount' => $quote['tax_amount'],
                    'merchandise_amount' => $quote['merchandise_amount'],
                    'return_shipping_cost' => $quote['return_shipping_cost'],
                    'shipping_treatment' => $quote['shipping_treatment'],
                    'shipping_deduction' => $quote['shipping_deduction'],
                    'company_shipping_loss' => $quote['company_shipping_loss'],
                    'customer_refund_amount' => $quote['customer_refund_amount'],
                    'reason' => $this->nullableText($data['reason'] ?? null),
                    'customer_note' => $this->nullableText($data['customer_note'] ?? null),
                    'internal_note' => $this->nullableText($data['internal_note'] ?? null),
                    'created_by' => $lockedAdmin->getKey(),
                    'refunded_at' => $timestamp,
                ]);
                $refund->items()->createMany($quote['items']);

                $target = $this->refundableItems($lockedOrder)->isEmpty()
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded;
                $this->payments->recordRefund(
                    $lockedOrder,
                    $payment,
                    $refund,
                    $target,
                    (int) $lockedAdmin->getKey(),
                );
                CommerceEventOccurred::dispatch(
                    NotificationEventCode::PaymentRefunded,
                    'refund',
                    (int) $refund->getKey(),
                );

                return $refund->fresh(['items', 'order', 'payment', 'creator']);
            });
        } catch (QueryException $exception) {
            if (! $this->isIdempotencyViolation($exception)) {
                throw $exception;
            }

            return Refund::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }
    }

    /**
     * @return Collection<int, array{
     *     order_item: OrderItem,
     *     refunded_quantity: string,
     *     remaining_quantity: string
     * }>
     */
    public function refundableItems(Order $order): Collection
    {
        $items = OrderItem::query()
            ->where('order_id', $order->getKey())
            ->financiallyRefundable()
            ->with('options')
            ->orderBy('id')
            ->get();
        $refunded = $this->refundedComponents($order, $items->pluck('id')->all());

        return $items->map(function (OrderItem $item) use ($refunded): array {
            $quantity = $this->component($refunded, $item->id, 'quantity');

            return [
                'order_item' => $item,
                'refunded_quantity' => $quantity,
                'remaining_quantity' => $this->subtract($item->quantity, $quantity),
            ];
        })->filter(fn (array $item): bool => $this->compare($item['remaining_quantity'], '0.0000') > 0)
            ->values();
    }

    /**
     * @param  array{items: array<int, array{order_item_id: mixed, quantity: mixed}>, return_shipping_cost: mixed, shipping_treatment: mixed}  $data
     * @return array<string, mixed>
     */
    public function quote(Order $order, array $data): array
    {
        $submitted = collect($data['items'] ?? [])->values();
        if ($submitted->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'At least one Refund Item is required.']);
        }

        $ids = $submitted->pluck('order_item_id')->map(fn ($id): int => (int) $id);
        if ($ids->contains(fn (int $id): bool => $id <= 0) || $ids->unique()->count() !== $ids->count()) {
            throw ValidationException::withMessages(['items' => 'Refund Items must be distinct valid Order Items.']);
        }

        $items = OrderItem::query()
            ->where('order_id', $order->getKey())
            ->whereIn('id', $ids)
            ->financiallyRefundable()
            ->orderBy('id')
            ->get()
            ->keyBy('id');
        if ($items->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'items' => 'Every selected Item must be a financially refundable Item belonging to this Order.',
            ]);
        }

        $previous = $this->refundedComponents($order, $ids->all());
        $refundItems = $submitted->map(function (array $requested) use ($items, $previous): array {
            $item = $items->get((int) $requested['order_item_id']);
            $quantity = $this->validatedPositiveDecimal(
                $requested['quantity'] ?? null,
                'items.'.$item->id.'.quantity'
            );
            $previousQuantity = $this->component($previous, $item->id, 'quantity');
            $newQuantity = $this->add($previousQuantity, $quantity);
            if ($this->compare($newQuantity, $item->quantity) > 0) {
                throw ValidationException::withMessages([
                    'items.'.$item->id.'.quantity' => 'Refund quantity exceeds the remaining refundable quantity.',
                ]);
            }

            $components = [];
            foreach ([
                'subtotal_amount' => 'row_subtotal',
                'discount_amount' => 'discount_amount',
                'tax_amount' => 'tax_amount',
                'line_amount' => 'row_total',
            ] as $refundField => $orderField) {
                $previousAmount = $this->component($previous, $item->id, $refundField);
                $target = $this->compare($newQuantity, $item->quantity) === 0
                    ? $this->decimal($item->{$orderField})
                    : $this->proportion($item->{$orderField}, $newQuantity, $item->quantity);
                $components[$refundField] = $this->subtract($target, $previousAmount);
            }

            if ($this->compare(
                $components['line_amount'],
                $this->add(
                    $this->subtract($components['subtotal_amount'], $components['discount_amount']),
                    $components['tax_amount']
                )
            ) !== 0) {
                throw ValidationException::withMessages([
                    'items.'.$item->id => 'The immutable Order Item financial snapshot is inconsistent.',
                ]);
            }

            return [
                'order_item_id' => (int) $item->id,
                'quantity' => $quantity,
                ...$components,
            ];
        })->sortBy('order_item_id')->values();

        $subtotal = $this->sum($refundItems->pluck('subtotal_amount'));
        $discount = $this->sum($refundItems->pluck('discount_amount'));
        $tax = $this->sum($refundItems->pluck('tax_amount'));
        $merchandise = $this->sum($refundItems->pluck('line_amount'));
        $shippingCost = $this->validatedNonNegativeDecimal(
            $data['return_shipping_cost'] ?? null,
            'return_shipping_cost'
        );
        try {
            $treatment = ShippingTreatment::from((string) ($data['shipping_treatment'] ?? ''));
        } catch (\ValueError) {
            throw ValidationException::withMessages([
                'shipping_treatment' => 'A valid Shipping Treatment is required.',
            ]);
        }
        $deduction = $treatment === ShippingTreatment::DeductFromRefund
            ? $shippingCost
            : '0.0000';
        $companyLoss = $treatment === ShippingTreatment::CompanyAbsorbs
            ? $shippingCost
            : '0.0000';
        $customerAmount = $this->subtract($merchandise, $deduction);
        if ($this->compare($customerAmount, '0.0000') <= 0) {
            throw ValidationException::withMessages([
                'return_shipping_cost' => 'The Shipping deduction must leave a positive Customer Refund amount.',
            ]);
        }

        return [
            'items' => $refundItems->all(),
            'merchandise_subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'merchandise_amount' => $merchandise,
            'return_shipping_cost' => $shippingCost,
            'shipping_treatment' => $treatment,
            'shipping_deduction' => $deduction,
            'company_shipping_loss' => $companyLoss,
            'customer_refund_amount' => $customerAmount,
        ];
    }

    /** @param array<int, int> $itemIds */
    private function refundedComponents(Order $order, array $itemIds): Collection
    {
        if ($itemIds === []) {
            return collect();
        }

        return RefundItem::query()
            ->join('refunds', 'refunds.id', '=', 'refund_items.refund_id')
            ->where('refunds.order_id', $order->getKey())
            ->whereIn('refund_items.order_item_id', $itemIds)
            ->orderBy('refund_items.order_item_id')
            ->orderBy('refund_items.id')
            ->get([
                'refund_items.order_item_id',
                'refund_items.quantity',
                'refund_items.subtotal_amount',
                'refund_items.discount_amount',
                'refund_items.tax_amount',
                'refund_items.line_amount',
            ])
            ->groupBy('order_item_id')
            ->map(fn (Collection $rows): array => [
                'quantity' => $this->sum($rows->pluck('quantity')),
                'subtotal_amount' => $this->sum($rows->pluck('subtotal_amount')),
                'discount_amount' => $this->sum($rows->pluck('discount_amount')),
                'tax_amount' => $this->sum($rows->pluck('tax_amount')),
                'line_amount' => $this->sum($rows->pluck('line_amount')),
            ]);
    }

    private function component(Collection $components, int $itemId, string $field): string
    {
        return $components->get($itemId)[$field] ?? '0.0000';
    }

    private function validatedPositiveDecimal(mixed $value, string $field): string
    {
        $decimal = $this->validatedNonNegativeDecimal($value, $field);
        if ($this->compare($decimal, '0.0000') <= 0) {
            throw ValidationException::withMessages([$field => 'The quantity must be at least 0.0001.']);
        }

        return $decimal;
    }

    private function validatedNonNegativeDecimal(mixed $value, string $field): string
    {
        $string = is_string($value) || is_int($value) ? trim((string) $value) : null;
        if ($string === null || ! preg_match('/^(?:0|[1-9]\d{0,10})(?:\.\d{1,4})?$/', $string)) {
            throw ValidationException::withMessages([
                $field => 'The value must be a nonnegative decimal with at most four decimal places.',
            ]);
        }
        $decimal = $this->decimal($string);
        if ($this->compare($decimal, self::MAX_DECIMAL) > 0) {
            throw ValidationException::withMessages([$field => 'The value exceeds the supported amount.']);
        }

        return $decimal;
    }

    private function proportion(mixed $amount, mixed $quantity, mixed $totalQuantity): string
    {
        return BigDecimal::of((string) $amount)
            ->multipliedBy(BigDecimal::of((string) $quantity))
            ->dividedBy(BigDecimal::of((string) $totalQuantity), 4, RoundingMode::HalfUp)
            ->__toString();
    }

    private function sum(iterable $values): string
    {
        $sum = BigDecimal::zero();
        foreach ($values as $value) {
            $sum = $sum->plus((string) $value);
        }

        return $sum->toScale(4)->__toString();
    }

    private function add(mixed $left, mixed $right): string
    {
        return BigDecimal::of((string) $left)->plus((string) $right)->toScale(4)->__toString();
    }

    private function subtract(mixed $left, mixed $right): string
    {
        return BigDecimal::of((string) $left)->minus((string) $right)->toScale(4)->__toString();
    }

    private function compare(mixed $left, mixed $right): int
    {
        return BigDecimal::of((string) $left)->compareTo((string) $right);
    }

    private function decimal(mixed $value): string
    {
        return BigDecimal::of((string) $value)->toScale(4, RoundingMode::HalfUp)->__toString();
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function isIdempotencyViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && (str_contains($message, 'refunds_idempotency_key_unique')
                || str_contains($message, 'refunds.idempotency_key'));
    }
}
