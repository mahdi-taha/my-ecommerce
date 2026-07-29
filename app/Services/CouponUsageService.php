<?php

namespace App\Services;

use App\DTOs\Promotions\CouponUsageReleaseResult;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\CouponUsageRelease;
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

    public function release(Order $lockedOrder, string $reason): CouponUsageReleaseResult
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Coupon usage release requires an active database transaction.');
        }

        if (! $this->isEligibleTransition($lockedOrder, $reason)) {
            return new CouponUsageReleaseResult(CouponUsageReleaseResult::NOT_APPLICABLE);
        }

        $usage = CouponUsage::query()
            ->where('order_id', $lockedOrder->getKey())
            ->lockForUpdate()
            ->first();

        if (! $usage) {
            return new CouponUsageReleaseResult(CouponUsageReleaseResult::NOT_APPLICABLE);
        }

        $existing = CouponUsageRelease::query()
            ->where('coupon_usage_id', $usage->getKey())
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return new CouponUsageReleaseResult(
                CouponUsageReleaseResult::ALREADY_RELEASED,
                $existing
            );
        }

        $timestamp = now();
        $inserted = DB::table('coupon_usage_releases')->insertOrIgnore([
            'coupon_usage_id' => $usage->getKey(),
            'reason' => $reason,
            'released_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $release = CouponUsageRelease::query()
            ->where('coupon_usage_id', $usage->getKey())
            ->firstOrFail();

        return new CouponUsageReleaseResult(
            $inserted === 1
                ? CouponUsageReleaseResult::RELEASED
                : CouponUsageReleaseResult::ALREADY_RELEASED,
            $release
        );
    }

    private function isEligibleTransition(Order $order, string $reason): bool
    {
        return match ($reason) {
            'order_cancelled' => in_array($order->status, [
                OrderStatus::Pending->value,
                OrderStatus::Processing->value,
            ], true) && $order->fulfillment_status === FulfillmentStatus::Unfulfilled->value,
            'delivery_failed' => $order->status === OrderStatus::Processing->value
                && $order->fulfillment_status === FulfillmentStatus::OutForDelivery->value,
            default => false,
        };
    }
}
