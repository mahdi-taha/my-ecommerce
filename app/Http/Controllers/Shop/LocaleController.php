<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, ['en', 'ar'], true), 404);

        $request->session()->put('storefront_locale', $locale);

        $returnTo = (string) $request->input('return_to', '/');

        if (! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            $returnTo = '/';
        }

        return redirect()->to($returnTo);
    }
}
