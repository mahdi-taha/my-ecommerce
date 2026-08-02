<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\CustomerLoginRequest;
use App\Http\Requests\CustomerRegistrationRequest;
use App\Services\CartService;
use App\Services\CustomerService;
use App\Services\GuestCartTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Throwable;

class CustomerAuthController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private GuestCartTokenService $tokenService,
        private CustomerService $customerService
    ) {}

    public function showLogin(Request $request): View
    {
        $this->rememberCustomerReturnTo($request);

        return view('customer.auth.login');
    }

    public function showRegistration(Request $request): View
    {
        $this->rememberCustomerReturnTo($request);

        return view('customer.auth.register');
    }

    public function register(CustomerRegistrationRequest $request): RedirectResponse
    {
        $customer = $this->customerService->register($request->validated());
        Auth::guard('customer')->login($customer);
        $request->clearRateLimiter();
        $request->session()->regenerate();
        $customer->update(['last_login_at' => now()]);
        $guestToken = $this->tokenService->fromRequest($request);
        $warnings = $this->cartService->mergeGuestCart($customer, $guestToken);
        $response = $this->authenticationRedirect($request)
            ->with('success', __('shop.auth.register.success'));

        if ($warnings !== []) {
            $response->with('warning', implode(' ', $warnings));
        }

        return $guestToken
            ? $response->withCookie($this->tokenService->forgetCookie())
            : $response;
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
        $response = $this->authenticationRedirect($request);

        if ($warnings !== []) {
            $response->with('warning', implode(' ', $warnings));
        }

        return $guestToken
            ? $response->withCookie($this->tokenService->forgetCookie())
            : $response;
    }

    public function logout(Request $request): RedirectResponse
    {
        $destination = $this->storefrontDestination($request->input('return_to'))
            ?? route('shop.home');

        Auth::guard('customer')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->to($destination);
    }

    private function rememberCustomerReturnTo(Request $request): void
    {
        $destination = $this->storefrontDestination($request->query('return_to'));

        if ($destination !== null) {
            $request->session()->put('customer_return_to', $destination);
        }
    }

    private function authenticationRedirect(Request $request): RedirectResponse
    {
        $destination = $this->storefrontDestination(
            $request->session()->pull('customer_return_to')
        );

        if ($destination !== null) {
            $request->session()->forget('url.intended');

            return redirect()->to($destination);
        }

        return redirect()->intended(route('customer.account.edit'));
    }

    private function storefrontDestination(mixed $returnTo): ?string
    {
        if (! is_string($returnTo) || $returnTo === '') {
            return null;
        }

        $target = parse_url($returnTo);
        $application = parse_url((string) config('app.url'));

        if ($target === false || $application === false
            || isset($target['user']) || isset($target['pass'])
            || strtolower((string) ($target['scheme'] ?? '')) !== strtolower((string) ($application['scheme'] ?? ''))
            || strtolower((string) ($target['host'] ?? '')) !== strtolower((string) ($application['host'] ?? ''))
            || $this->urlPort($target) !== $this->urlPort($application)) {
            return null;
        }

        try {
            $routeName = Route::getRoutes()
                ->match(Request::create($returnTo, 'GET'))
                ->getName();
        } catch (Throwable) {
            return null;
        }

        return in_array($routeName, [
            'shop.home',
            'shop.products.show',
            'shop.categories.show',
            'shop.cart.index',
        ], true) ? $returnTo : null;
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function urlPort(array $parts): ?int
    {
        if (isset($parts['port'])) {
            return (int) $parts['port'];
        }

        return match (strtolower((string) ($parts['scheme'] ?? ''))) {
            'http' => 80,
            'https' => 443,
            default => null,
        };
    }
}
