<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\AdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('admin.auth.login');
    }

    public function login(AdminLoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $request->ensureIsNotRateLimited();

        $remember = (bool) ($credentials['remember'] ?? false);
        unset($credentials['remember']);
        $credentials['account_type'] = AccountType::Admin->value;
        $credentials['has_account'] = true;
        $credentials['is_active'] = true;

        if (! Auth::guard('admin')->attempt($credentials, $remember)) {
            $request->hitRateLimiter();

            return back()
                ->withErrors([
                    'email' => 'The provided credentials are invalid.',
                ])
                ->onlyInput('email');
        }

        $request->clearRateLimiter();
        $request->session()->regenerate();
        $request->user('admin')->update([
            'last_login_at' => now(),
        ]);

        return redirect()->intended(route('admin.products.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
