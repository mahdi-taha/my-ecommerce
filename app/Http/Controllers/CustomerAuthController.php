<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\CustomerLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('customer.auth.login');
    }

    public function login(CustomerLoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $remember = (bool) ($credentials['remember'] ?? false);
        unset($credentials['remember']);
        $credentials['account_type'] = AccountType::Customer->value;
        $credentials['has_account'] = true;
        $credentials['is_active'] = true;

        if (! Auth::guard('customer')->attempt($credentials, $remember)) {
            return back()->withErrors([
                'email' => 'The provided credentials are invalid.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->user('customer')->update(['last_login_at' => now()]);

        return redirect()->intended(route('customer.account.edit'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }
}
