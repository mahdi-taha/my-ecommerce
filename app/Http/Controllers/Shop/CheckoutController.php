<?php

namespace App\Http\Controllers\Shop;

use App\Enums\PaymentMethodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutOrderPlacementService;
use App\Services\CheckoutService;
use App\Services\GuestCartTokenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    private const GUEST_ORDER_SESSION_KEY = 'shop.checkout.guest_orders';

    public function __construct(
        private CartService $cartService,
        private GuestCartTokenService $tokenService,
        private CheckoutService $checkoutService,
        private CheckoutOrderPlacementService $placementService,
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

        return view('shop.pages.checkout', compact(
            'customer',
            'shippingMethods',
            'paymentMethods',
            'shippingCode',
            'paymentCode',
            'summary'
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

        return view('shop.pages.checkout-success', compact('order'));
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
            ->whereIn('type', [
                PaymentMethodType::Offline->value,
                PaymentMethodType::ManualTransfer->value,
            ])
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
        ];

        if ($code === 'guest_checkout_disabled') {
            return redirect()->guest(route('customer.login'))
                ->with('warning', __('shop.checkout.failures.'.$code));
        }

        $route = in_array($code, $cartCodes, true)
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
}
