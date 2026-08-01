<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\CustomerLoginRequest;
use App\Services\CartService;
use App\Services\GuestCartTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private GuestCartTokenService $tokenService
    ) {}

    public function showLogin(): View
    {
        return view('customer.auth.login');
    }

    public function login(CustomerLoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $request->ensureIsNotRateLimited();
        $remember = (bool) ($credentials['remember'] ?? false);
        unset($credentials['remember']);
        $credentials['account_type'] = AccountType::Customer->value;
        $credentials['has_account'] = true;
        $credentials['is_active'] = true;

        if (! Auth::guard('customer')->attempt($credentials, $remember)) {
            $request->hitRateLimiter();

            return back()->withErrors([
                'email' => $request->loginFailureMessage(),
            ])->onlyInput('email');
        }

        $request->clearRateLimiter();
        $request->session()->regenerate();
        $customer = $request->user('customer');
        $customer->update(['last_login_at' => now()]);
        $guestToken = $this->tokenService->fromRequest($request);
        $warnings = $this->cartService->mergeGuestCart($customer, $guestToken);
        $response = redirect()->intended(route('customer.account.edit'));

        if ($warnings !== []) {
            $response->with('warning', implode(' ', $warnings));
        }

        return $guestToken
            ? $response->withCookie($this->tokenService->forgetCookie())
            : $response;
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }
}
