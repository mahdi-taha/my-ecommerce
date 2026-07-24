<?php

namespace App\Http\Controllers\Shop;

use App\Enums\CartItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\User;
use App\Services\CartService;
use App\Services\GuestCartTokenService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private GuestCartTokenService $tokenService
    ) {}

    public function index(Request $request): View
    {
        return view('shop.pages.cart', $this->cartService->summary(
            $this->customer(),
            $this->tokenService->fromRequest($request)
        ));
    }

    public function store(AddCartItemRequest $request): RedirectResponse
    {
        $customer = $this->customer();
        $guestToken = $this->tokenService->fromRequest($request);
        $newGuestToken = null;

        if (! $customer && ! $guestToken) {
            $guestToken = $newGuestToken = $this->tokenService->generate();
        }

        $validated = $request->validated();

        if ($validated['product_type'] === CartItemType::Configurable->value) {
            $this->cartService->addConfigurable(
                $customer,
                $guestToken,
                (int) $validated['product_id'],
                $validated['options'],
                (int) $validated['quantity']
            );
        } else {
            $this->cartService->addSimple(
                $customer,
                $guestToken,
                (int) $validated['product_id'],
                (int) $validated['quantity']
            );
        }

        $response = redirect()
            ->route('shop.cart.index')
            ->with('success', __('shop.cart.messages.added'));

        return $newGuestToken
            ? $response->withCookie($this->tokenService->cookie(
                $newGuestToken,
                max(1, (int) setting('cart.lifetime_days', 30))
            ))
            : $response;
    }

    public function update(
        UpdateCartItemRequest $request,
        int $cartItem
    ): RedirectResponse {
        $this->cartService->updateQuantity(
            $this->customer(),
            $this->tokenService->fromRequest($request),
            $cartItem,
            (int) $request->validated('quantity')
        );

        return back()->with('success', __('shop.cart.messages.updated'));
    }

    public function destroy(Request $request, int $cartItem): RedirectResponse
    {
        $this->cartService->remove(
            $this->customer(),
            $this->tokenService->fromRequest($request),
            $cartItem
        );

        return back()->with('success', __('shop.cart.messages.removed'));
    }

    public function clear(Request $request): RedirectResponse
    {
        $this->cartService->clear(
            $this->customer(),
            $this->tokenService->fromRequest($request)
        );

        return back()->with('success', __('shop.cart.messages.cleared'));
    }

    private function customer(): ?User
    {
        return Auth::guard('customer')->user();
    }
}
