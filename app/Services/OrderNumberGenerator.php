<?php

namespace App\Services;

use App\Models\Order;
use RuntimeException;

class OrderNumberGenerator
{
    private const PREFIX = 'ORD-';

    private const MAX_NUMBER = 99_999_999;

    public function generate(): string
    {
        $latestOrder = Order::query()
            ->orderByDesc('id')
            ->first(['id', 'order_number']);

        if (! $latestOrder) {
            return self::PREFIX.'00000001';
        }

        if (! preg_match('/^ORD-(\d{8})$/', $latestOrder->order_number, $matches)) {
            throw new RuntimeException(
                "Order {$latestOrder->id} has an invalid order number: {$latestOrder->order_number}."
            );
        }

        $nextNumber = (int) $matches[1] + 1;

        if ($nextNumber > self::MAX_NUMBER) {
            throw new RuntimeException('The eight-digit order number sequence has been exhausted.');
        }

        return self::PREFIX.sprintf('%08d', $nextNumber);
    }
}
