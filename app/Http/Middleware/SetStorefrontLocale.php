<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetStorefrontLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin', 'admin/*')) {
            $locale = $request->session()->get(
                'storefront_locale',
                setting('localization.default_locale', config('app.locale'))
            );

            if (in_array($locale, ['en', 'ar'], true)) {
                app()->setLocale($locale);
            }
        }

        return $next($request);
    }
}
