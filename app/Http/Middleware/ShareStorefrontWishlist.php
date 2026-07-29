<?php

namespace App\Http\Middleware;

use App\Models\WishlistItem;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareStorefrontWishlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $customerId = Auth::guard('customer')->id();
        $count = $customerId
            ? WishlistItem::query()
                ->whereHas('wishlist', fn ($query) => $query->where('user_id', $customerId))
                ->count()
            : 0;

        View::share('storefrontWishlistCount', $count);

        return $next($request);
    }
}
