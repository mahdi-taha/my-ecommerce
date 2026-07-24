<?php

namespace App\Http\Middleware;

use App\Enums\AccountType;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('customer');

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        abort_unless(
            $user->account_type === AccountType::Customer && $user->has_account && $user->is_active,
            403
        );

        return $next($request);
    }
}
