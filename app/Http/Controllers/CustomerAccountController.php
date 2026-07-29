<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCustomerAccountPasswordRequest;
use App\Http\Requests\UpdateCustomerProfileRequest;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    public function __construct(private CustomerService $customerService) {}

    public function edit(Request $request): View
    {
        return view('customer.account.edit', ['customer' => $request->user('customer')]);
    }

    public function update(UpdateCustomerProfileRequest $request): RedirectResponse
    {
        $this->customerService->updateProfile($request->user('customer'), $request->validated());

        return back()->with('success', __('shop.account.profile.updated'));
    }

    public function editPassword(): View
    {
        return view('customer.account.password');
    }

    public function updatePassword(UpdateCustomerAccountPasswordRequest $request): RedirectResponse
    {
        $this->customerService->updateOwnPassword(
            $request->user('customer'),
            $request->validated('password')
        );

        return back()->with('success', 'Password updated successfully.');
    }
}
