<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\StorefrontLocaleUrlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(
        Request $request,
        string $targetLocale,
        StorefrontLocaleUrlService $urls,
    ): RedirectResponse {
        abort_unless($urls->supported(app()->getLocale()) && $urls->supported($targetLocale), 404);

        $destination = $urls->equivalentFromSession($request, $targetLocale);
        $request->session()->put('storefront_locale', $targetLocale);

        return redirect()->to($destination);
    }
}
