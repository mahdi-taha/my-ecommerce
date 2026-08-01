<?php

namespace App\Http\Middleware;

use App\Enums\AccountType;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceActiveCustomerSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('customer');
        $customer = $guard->user();

        if ($customer
            && ($customer->account_type !== AccountType::Customer
                || ! $customer->has_account
                || ! $customer->is_active)) {
            $guard->logout();
            $request->session()->flash('error', __('shop.auth.account_inactive'));
        }

        return $next($request);
    }
}
