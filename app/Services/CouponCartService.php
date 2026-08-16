<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponCartService
{
    public function __construct(private CouponEligibilityService $eligibilityService) {}

    public function apply(
        Cart $cart,
        mixed $code,
        string $eligibleSubtotal,
        ?User $customer
    ): Cart {
        return DB::transaction(function () use ($cart, $code, $eligibleSubtotal, $customer): Cart {
            $lockedCart = Cart::query()->lockForUpdate()->findOrFail($cart->getKey());
            $normalized = $this->eligibilityService->normalizeCode($code);
            $coupon = Coupon::query()->where('code', $normalized)->lockForUpdate()->first();

            if (! $coupon) {
                throw ValidationException::withMessages([
                    'coupon_code' => __('shop.checkout.coupon.errors.coupon_not_found'),
                ]);
            }

            $errors = $this->eligibilityService->validate($coupon, $eligibleSubtotal, $customer);

            if ($errors !== []) {
                throw ValidationException::withMessages([
                    'coupon_code' => __('shop.checkout.coupon.errors.'.$errors[0]),
                ]);
            }

            if ((int) $lockedCart->coupon_id !== (int) $coupon->getKey()) {
                $timestamp = now();
                $lockedCart->update([
                    'coupon_id' => $coupon->getKey(),
                    'last_activity_at' => $timestamp,
                    'expires_at' => $timestamp->copy()->addDays(
                        max(1, (int) setting('cart.lifetime_days', 30))
                    ),
                ]);
            }

            return $lockedCart->fresh('coupon');
        });
    }

    public function remove(Cart $cart): Cart
    {
        return DB::transaction(function () use ($cart): Cart {
            $lockedCart = Cart::query()->lockForUpdate()->findOrFail($cart->getKey());

            if ($lockedCart->coupon_id !== null) {
                $timestamp = now();
                $lockedCart->update([
                    'coupon_id' => null,
                    'last_activity_at' => $timestamp,
                    'expires_at' => $timestamp->copy()->addDays(
                        max(1, (int) setting('cart.lifetime_days', 30))
                    ),
                ]);
            }

            return $lockedCart->fresh();
        });
    }

    /** @return list<string> */
    public function errors(Cart $cart, string $eligibleSubtotal, ?User $customer): array
    {
        $cart->loadMissing('coupon');

        if (! $cart->coupon_id || ! $cart->coupon) {
            return $cart->coupon_id ? ['coupon_not_found'] : [];
        }

        return $this->eligibilityService->validate(
            $cart->coupon,
            $eligibleSubtotal,
            $customer
        );
    }
}
