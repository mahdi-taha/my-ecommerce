<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

class SafeStorefrontContentUrl implements ValidationRule
{
    private const ROUTES = ['shop.home', 'shop.products.index', 'shop.products.show', 'shop.categories.show', 'shop.pages.show', 'shop.cart.index'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }
        $url = trim((string) $value);
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user'],$parts['pass']) || isset($parts['fragment'])) {
            $fail('The :attribute must be a safe storefront URL.');

            return;
        }
        if (isset($parts['scheme'])) {
            if (strtolower($parts['scheme']) !== 'https' || empty($parts['host'])) {
                $fail('The :attribute must use HTTPS.');
            }

            return;
        }
        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            $fail('The :attribute must be a safe storefront URL.');

            return;
        }
        try {
            $route = Route::getRoutes()->match(Request::create($url, 'GET'));
        } catch (Throwable) {
            $fail('The :attribute must resolve to a public storefront page.');

            return;
        }
        if (! in_array($route->getName(), self::ROUTES, true)) {
            $fail('The :attribute must resolve to a public storefront page.');
        }
    }
}
