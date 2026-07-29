<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\CheckoutSummaryRequest;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Presenters\ManualPaymentInstructionsPresenter;
use App\Services\CartService;
use App\Services\CheckoutOrderPlacementService;
use App\Services\CheckoutService;
use App\Services\GuestCartTokenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    private const GUEST_ORDER_SESSION_KEY = 'shop.checkout.guest_orders';

    private const SUPPORTED_PAYMENT_METHODS = [
        'cash_on_delivery',
        'manual_wallet_transfer',
        'manual_bank_transfer',
    ];

    public function __construct(
        private CartService $cartService,
        private GuestCartTokenService $tokenService,
        private CheckoutService $checkoutService,
        private CheckoutOrderPlacementService $placementService,
        private ManualPaymentInstructionsPresenter $paymentInstructions,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $customer = $this->customer();

        if (! $customer && ! $this->guestCheckoutAllowed()) {
            return redirect()->guest(route('customer.login'));
        }

        $cart = $this->resolveCart($request, $customer);

        if (! $cart || ! $cart->items()->exists()) {
            return redirect()->route('shop.cart.index')
                ->with('warning', __('shop.checkout.failures.empty_cart'));
        }

        $shippingMethods = ShippingMethod::query()->activeOrdered()->get();
        $paymentMethods = $this->supportedPaymentMethods()->get();
        $shippingCode = $this->selectedCode(
            $request,
            'shipping_method',
            $shippingMethods->pluck('code')->all()
        );
        $paymentCode = $this->selectedCode(
            $request,
            'payment_method',
            $paymentMethods->pluck('code')->all()
        );
        $summary = $this->checkoutService->summarize(
            $cart,
            $shippingCode,
            $paymentCode
        );
        $savedAddresses = $customer
            ? $customer->customerAddresses()
                ->orderByDesc('is_default_shipping')
                ->orderByDesc('is_default_billing')
                ->latest('created_at')
                ->latest('id')
                ->get()
            : collect();
        $defaultShippingAddress = $savedAddresses->firstWhere('is_default_shipping', true);

        return view('shop.pages.checkout', compact(
            'customer',
            'shippingMethods',
            'paymentMethods',
            'shippingCode',
            'paymentCode',
            'summary',
            'savedAddresses',
            'defaultShippingAddress'
        ));
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $customer = $this->customer();
        $guestToken = $this->tokenService->fromRequest($request);
        $cart = $this->cartService->resolve($customer, $guestToken);

        if (! $cart) {
            return redirect()->route('shop.cart.index')
                ->with('warning', __('shop.checkout.failures.empty_cart'));
        }

        $result = $this->placementService->place(
            $cart,
            $request->validated(),
            $customer,
            $guestToken
        );

        if (! $result->successful) {
            return $this->failureResponse($result->failureCodes());
        }

        if (! $customer) {
            $orders = (array) $request->session()->get(self::GUEST_ORDER_SESSION_KEY, []);
            $orders[(string) $result->order->getKey()] = $cart->getKey();
            $request->session()->put(self::GUEST_ORDER_SESSION_KEY, $orders);
        }

        return redirect()->route('shop.checkout.success', $result->order);
    }

    public function summary(CheckoutSummaryRequest $request): JsonResponse
    {
        $customer = $this->customer();

        if (! $customer && ! $this->guestCheckoutAllowed()) {
            return $this->summaryFailure('guest_checkout_disabled', 'customer');
        }

        $cart = $this->resolveCart($request, $customer);

        if (! $cart || ! $cart->items()->exists()) {
            return $this->summaryFailure('empty_cart', 'cart');
        }

        $validated = $request->validated();
        $paymentMethodCode = $validated['payment_method']
            ?? $this->supportedPaymentMethods()->value('code');
        $summary = $this->checkoutService->summarize(
            $cart,
            $validated['shipping_method'],
            (string) $paymentMethodCode
        );

        if (! $summary->isValid()) {
            $errors = collect($summary->errors)->map(function (array $error): array {
                $code = $error['code'] ?? 'order_placement_failed';

                return [
                    'code' => $code,
                    'field' => $error['field'] ?? 'checkout',
                    'cart_item_id' => $error['cart_item_id'] ?? null,
                    'product_id' => $error['product_id'] ?? null,
                    'message' => __('shop.checkout.failures.'.$code),
                ];
            })->all();

            return response()->json(['success' => false, 'errors' => $errors], 422);
        }

        return response()->json([
            'success' => true,
            'summary' => [
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
            ],
        ]);
    }

    public function success(Request $request, Order $order): View
    {
        $this->authorizeConfirmation($request, $order);

        $order->load([
            'billingAddress',
            'shippingAddress',
            'shipping',
            'payment',
            'items.options',
        ]);

        $manualPayment = $this->paymentInstructions->present($order);

        return view('shop.pages.checkout-success', compact('order', 'manualPayment'));
    }

    private function resolveCart(Request $request, ?User $customer): ?Cart
    {
        return $this->cartService->resolve(
            $customer,
            $this->tokenService->fromRequest($request)
        );
    }

    private function customer(): ?User
    {
        return Auth::guard('customer')->user();
    }

    private function supportedPaymentMethods(): Builder
    {
        return PaymentMethod::query()
            ->where('is_active', true)
            ->whereIn('code', self::SUPPORTED_PAYMENT_METHODS)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    private function selectedCode(Request $request, string $key, array $availableCodes): string
    {
        $selected = $request->old($key, $request->query($key));

        return is_string($selected) && in_array($selected, $availableCodes, true)
            ? $selected
            : ($availableCodes[0] ?? '');
    }

    private function failureResponse(array $codes): RedirectResponse
    {
        $code = $codes[0] ?? 'order_placement_failed';
        $cartCodes = [
            'cart_ownership_mismatch',
            'cart_changed',
            'empty_cart',
            'product_unavailable',
            'product_inactive',
            'product_not_visible',
            'invalid_configuration',
            'invalid_quantity',
            'insufficient_stock',
            'coupon_invalid',
        ];

        if ($code === 'guest_checkout_disabled') {
            return redirect()->guest(route('customer.login'))
                ->with('warning', __('shop.checkout.failures.'.$code));
        }

        $route = in_array($code, $cartCodes, true) && $code !== 'coupon_invalid'
            ? 'shop.cart.index'
            : 'shop.checkout.show';

        return redirect()->route($route)
            ->withInput()
            ->with('error', __('shop.checkout.failures.'.$code));
    }

    private function authorizeConfirmation(Request $request, Order $order): void
    {
        $customer = $this->customer();

        if ($order->user_id !== null) {
            abort_unless($customer && (int) $order->user_id === (int) $customer->getKey(), 403);

            return;
        }

        abort_if($customer, 403);
        $guestOrders = (array) $request->session()->get(self::GUEST_ORDER_SESSION_KEY, []);
        $cartId = $guestOrders[(string) $order->getKey()] ?? null;
        $token = $this->tokenService->fromRequest($request);
        $cart = $cartId ? Cart::query()->find($cartId) : null;

        abort_unless(
            $cart
                && $cart->user_id === null
                && $token
                && $cart->guest_token_hash
                && hash_equals($cart->guest_token_hash, $this->tokenService->hash($token)),
            403
        );
    }

    private function guestCheckoutAllowed(): bool
    {
        return filter_var(setting('checkout.allow_guest_checkout', true), FILTER_VALIDATE_BOOL);
    }

    private function summaryFailure(string $code, string $field): JsonResponse
    {
        return response()->json([
            'success' => false,
            'errors' => [[
                'code' => $code,
                'field' => $field,
                'message' => __('shop.checkout.failures.'.$code),
            ]],
        ], 422);
    }
}
