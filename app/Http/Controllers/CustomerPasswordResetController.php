<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerForgotPasswordRequest;
use App\Http\Requests\CustomerResetPasswordRequest;
use App\Services\CustomerPasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerPasswordResetController extends Controller
{
    public function __construct(private CustomerPasswordResetService $passwordResetService) {}

    public function create(): View
    {
        return view('customer.auth.forgot-password');
    }

    public function store(CustomerForgotPasswordRequest $request): RedirectResponse
    {
        $this->passwordResetService->sendResetLink(
            $request->validated('email'),
            app()->getLocale()
        );

        return back()->with('status', __('shop.auth.password.generic_response'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('customer.auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function update(CustomerResetPasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (! $this->passwordResetService->reset(
            $validated['email'],
            $validated['token'],
            $validated['password']
        )) {
            return back()->withErrors([
                'email' => __('shop.auth.password.reset_failed'),
            ])->onlyInput('email');
        }

        $request->clearRateLimiter();

        return redirect()->route('customer.login')
            ->with('status', __('shop.auth.password.reset_success'));
    }
}
