<?php

namespace App\Http\Middleware;

use App\Services\CartService;
use App\Services\GuestCartTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveStorefrontCart
{
    public function __construct(
        private CartService $cartService,
        private GuestCartTokenService $tokenService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        View::share('storefrontCartQuantity', $this->cartService->quantity(
            Auth::guard('customer')->user(),
            $this->tokenService->fromRequest($request)
        ));

        return $next($request);
    }
}
