<?php

namespace App\Http\Middleware;

use App\Services\StorefrontLocaleUrlService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetStorefrontLocale
{
    public function __construct(private StorefrontLocaleUrlService $urls) {}

    public function handle(Request $request, Closure $next): Response
    {
        $localizedRequest = false;
        $adminRequest = $request->is('admin', 'admin/*');

        if ($adminRequest) {
            app()->setLocale('en');
            URL::defaults(['locale' => null]);
        } else {
            $routeLocale = $request->route('locale');
            $locale = is_string($routeLocale) && in_array($routeLocale, ['en', 'ar'], true)
                ? $routeLocale
                : $request->session()->get(
                    'storefront_locale',
                    setting('localization.default_locale', config('app.locale'))
                );

            if (in_array($locale, ['en', 'ar'], true)) {
                app()->setLocale($locale);
                URL::defaults(['locale' => $locale]);

                if ($routeLocale !== null) {
                    $localizedRequest = true;
                    $request->session()->put('storefront_locale', $locale);
                    $request->route()->forgetParameter('locale');
                }
            }
        }

        $response = $next($request);

        if ($localizedRequest) {
            $this->urls->capture($request);
        }

        if ($adminRequest) {
            URL::defaults(['locale' => $this->urls->defaultLocale()]);
        }

        return $response;
    }
}
