<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Models\Coupon;

class CouponAllocationService
{
    /**
     * @param  array<int, array{cart_item_id: int, subtotal: string}>  $items
     * @return array{discount_total: string, allocations: array<int, string>}
     */
    public function allocate(Coupon $coupon, array $items): array
    {
        $ordered = collect($items)
            ->filter(fn (array $item): bool => $this->units($item['subtotal']) > 0)
            ->sortBy('cart_item_id')
            ->values();
        $subtotalUnits = $ordered->sum(
            fn (array $item): int => $this->units($item['subtotal'])
        );

        if ($subtotalUnits <= 0) {
            return ['discount_total' => '0.0000', 'allocations' => []];
        }

        $discountUnits = $coupon->type === CouponType::Percentage
            ? (int) round($subtotalUnits * (float) $coupon->value / 100)
            : $this->units($coupon->value);
        $discountUnits = max(0, min($subtotalUnits, $discountUnits));
        $remaining = $discountUnits;
        $allocations = [];

        foreach ($ordered as $index => $item) {
            $lineUnits = $this->units($item['subtotal']);
            $isLast = $index === $ordered->count() - 1;
            $allocation = $isLast
                ? min($lineUnits, $remaining)
                : min($lineUnits, (int) floor($discountUnits * $lineUnits / $subtotalUnits));
            $allocations[(int) $item['cart_item_id']] = $this->decimal($allocation);
            $remaining -= $allocation;
        }

        if ($remaining > 0 && $ordered->isNotEmpty()) {
            $last = (int) $ordered->last()['cart_item_id'];
            $allocations[$last] = $this->decimal(
                $this->units($allocations[$last]) + $remaining
            );
        }

        return [
            'discount_total' => $this->decimal($discountUnits),
            'allocations' => $allocations,
        ];
    }

    private function units(string|int|float $amount): int
    {
        return (int) round((float) $amount * 10000);
    }

    private function decimal(int $units): string
    {
        return number_format($units / 10000, 4, '.', '');
    }
}
