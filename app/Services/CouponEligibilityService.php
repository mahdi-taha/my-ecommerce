<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class CouponEligibilityService
{
    public function normalizeCode(mixed $code): string
    {
        return mb_strtoupper(trim((string) $code), 'UTF-8');
    }

    public function resolve(mixed $code): ?Coupon
    {
        $normalized = $this->normalizeCode($code);

        return $normalized === ''
            ? null
            : Coupon::query()->where('code', $normalized)->first();
    }

    /**
     * @return list<string>
     */
    public function validate(
        Coupon $coupon,
        string|int|float $eligibleSubtotal,
        ?User $customer = null,
        ?CarbonInterface $at = null
    ): array {
        $errors = $this->configurationErrors($coupon);
        $instant = ($at
            ? CarbonImmutable::instance($at)
            : CarbonImmutable::now($this->storeTimezone()))->utc();

        if (! $coupon->is_active) {
            $errors[] = 'coupon_inactive';
        }

        if ($coupon->starts_at && $instant->lt($coupon->starts_at)) {
            $errors[] = 'coupon_not_started';
        }

        if ($coupon->ends_at && ! $instant->lt($coupon->ends_at)) {
            $errors[] = 'coupon_expired';
        }

        if ($coupon->minimum_subtotal !== null
            && (float) $eligibleSubtotal < (float) $coupon->minimum_subtotal) {
            $errors[] = 'coupon_minimum_not_met';
        }

        if ($customer === null) {
            if ($coupon->is_first_order_only) {
                $errors[] = 'coupon_requires_customer';
            }

            if ($coupon->per_customer_usage_limit !== null) {
                $errors[] = 'coupon_customer_limit_requires_customer';
            }
        } elseif (! $this->eligibleCustomer($customer)) {
            $errors[] = 'coupon_customer_ineligible';
        }

        if ($coupon->usage_limit !== null
            && $coupon->unreleasedUsages()->count() >= $coupon->usage_limit) {
            $errors[] = 'coupon_usage_limit_reached';
        }

        if ($customer && $coupon->per_customer_usage_limit !== null
            && $coupon->unreleasedUsages()->where('user_id', $customer->getKey())->count()
                >= $coupon->per_customer_usage_limit) {
            $errors[] = 'coupon_customer_limit_reached';
        }

        if ($customer && $coupon->is_first_order_only) {
            if ($customer->orders()->where('status', OrderStatus::Completed->value)->exists()
                || CouponUsage::query()
                    ->where('user_id', $customer->getKey())
                    ->whereDoesntHave('release')
                    ->whereHas('coupon', fn ($query) => $query->where('is_first_order_only', true))
                    ->exists()) {
                $errors[] = 'coupon_first_order_ineligible';
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @return list<string>
     */
    public function configurationErrors(Coupon $coupon): array
    {
        $errors = [];
        $value = (float) $coupon->value;

        if ($value <= 0
            || ($coupon->type === CouponType::Percentage && $value > 100)) {
            $errors[] = 'coupon_value_invalid';
        }

        if ($coupon->starts_at && $coupon->ends_at && $coupon->ends_at->lte($coupon->starts_at)) {
            $errors[] = 'coupon_date_range_invalid';
        }

        if ($coupon->usage_limit !== null && $coupon->usage_limit <= 0) {
            $errors[] = 'coupon_usage_limit_invalid';
        }

        if ($coupon->per_customer_usage_limit !== null && $coupon->per_customer_usage_limit <= 0) {
            $errors[] = 'coupon_customer_limit_invalid';
        }

        return $errors;
    }

    private function eligibleCustomer(User $customer): bool
    {
        return $customer->account_type === AccountType::Customer
            && $customer->has_account
            && $customer->is_active;
    }

    private function storeTimezone(): string
    {
        return (string) setting('localization.timezone', config('app.timezone'));
    }
}
