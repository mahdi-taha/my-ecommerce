<?php

namespace App\Http\Controllers\Shop;

use App\DTOs\Checkout\CheckoutSummary;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyCheckoutCouponRequest;
use App\Http\Requests\CheckoutSummaryRequest;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\CouponCartService;
use App\Services\GuestCartTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CheckoutCouponController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private GuestCartTokenService $tokenService,
        private CheckoutService $checkoutService,
        private CouponCartService $couponCartService,
    ) {}

    public function store(ApplyCheckoutCouponRequest $request): JsonResponse|RedirectResponse
    {
        $customer = $this->customer();
        $cart = $this->cartService->resolve($customer, $this->tokenService->fromRequest($request));

        if (! $cart || ! $cart->items()->exists()) {
            return $this->failure($request, __('shop.checkout.failures.empty_cart'));
        }

        $validated = $request->validated();
        $base = $this->checkoutService->summarize(
            $cart,
            $validated['shipping_method'],
            $validated['payment_method']
        );

        if (! $base->isValid()) {
            return $this->failure($request, $base->errors[0]['message']);
        }

        try {
            $this->couponCartService->apply(
                $cart,
                $validated['coupon_code'],
                $base->subtotal,
                $customer
            );
        } catch (ValidationException $exception) {
            return $this->failure(
                $request,
                $exception->errors()['coupon_code'][0] ?? __('shop.checkout.coupon.errors.coupon_not_found')
            );
        }
        $summary = $this->checkoutService->summarize(
            $cart->fresh(),
            $validated['shipping_method'],
            $validated['payment_method']
        );

        return $this->response($request, $summary, __('shop.checkout.coupon.applied'));
    }

    public function destroy(CheckoutSummaryRequest $request): JsonResponse|RedirectResponse
    {
        $customer = $this->customer();
        $cart = $this->cartService->resolve($customer, $this->tokenService->fromRequest($request));

        if (! $cart) {
            return $this->failure($request, __('shop.checkout.failures.empty_cart'));
        }

        $this->couponCartService->remove($cart);
        $validated = $request->validated();
        $summary = $this->checkoutService->summarize(
            $cart->fresh(),
            $validated['shipping_method'],
            $validated['payment_method']
        );

        return $this->response($request, $summary, __('shop.checkout.coupon.removed'));
    }

    private function response(
        ApplyCheckoutCouponRequest|CheckoutSummaryRequest $request,
        CheckoutSummary $summary,
        string $message
    ): JsonResponse|RedirectResponse {
        if (! $request->expectsJson()) {
            return redirect()->route('shop.checkout.show')->with('success', $message);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'summary' => $this->summaryData($summary),
        ]);
    }

    private function failure(
        ApplyCheckoutCouponRequest|CheckoutSummaryRequest $request,
        string $message
    ): JsonResponse|RedirectResponse {
        if (! $request->expectsJson()) {
            return redirect()->route('shop.checkout.show')->with('error', $message);
        }

        return response()->json([
            'success' => false,
            'errors' => [['code' => 'coupon_invalid', 'field' => 'coupon_code', 'message' => $message]],
        ], 422);
    }

    private function summaryData(CheckoutSummary $summary): array
    {
        return [
            'subtotal' => $summary->subtotal,
            'discount_total' => $summary->discountTotal,
            'tax_total' => $summary->taxTotal,
            'shipping_amount' => $summary->shippingAmount,
            'grand_total' => $summary->grandTotal,
            'formatted_subtotal' => format_store_price($summary->subtotal, $summary->currencyCode),
            'formatted_discount_total' => format_store_price($summary->discountTotal, $summary->currencyCode),
            'formatted_tax_total' => format_store_price($summary->taxTotal, $summary->currencyCode),
            'formatted_shipping_amount' => format_store_price($summary->shippingAmount, $summary->currencyCode),
            'formatted_grand_total' => format_store_price($summary->grandTotal, $summary->currencyCode),
            'coupon' => $summary->coupon,
            'warnings' => $summary->warnings,
        ];
    }

    private function customer(): ?User
    {
        return Auth::guard('customer')->user();
    }
}
