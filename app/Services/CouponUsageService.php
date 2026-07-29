<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class CouponUsageService
{
    public function __construct(private CouponEligibilityService $eligibilityService) {}

    public function create(
        int $couponId,
        Order $order,
        string $eligibleSubtotal,
        string $discountAmount,
        ?User $customer
    ): CouponUsage {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Coupon usage creation requires an active database transaction.');
        }

        $coupon = Coupon::query()->whereKey($couponId)->lockForUpdate()->first();

        if (! $coupon) {
            throw ValidationException::withMessages(['coupon' => 'coupon_not_found']);
        }

        $errors = $this->eligibilityService->validate($coupon, $eligibleSubtotal, $customer);

        if ($errors !== []) {
            throw ValidationException::withMessages(['coupon' => $errors[0]]);
        }

        return CouponUsage::query()->create([
            'coupon_id' => $coupon->getKey(),
            'order_id' => $order->getKey(),
            'user_id' => $customer?->getKey(),
            'coupon_code' => $coupon->code,
            'coupon_type' => $coupon->type,
            'coupon_value' => $coupon->value,
            'eligible_subtotal' => $eligibleSubtotal,
            'discount_amount' => $discountAmount,
        ]);
    }

    public function release(CouponUsage $usage, string $reason): never
    {
        throw new LogicException('Coupon usage releases are implemented in Promotions Slice 3.');
    }
}
