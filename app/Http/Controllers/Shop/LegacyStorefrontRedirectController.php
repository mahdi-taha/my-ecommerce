<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\StorefrontLocaleUrlService;
use Illuminate\Http\RedirectResponse;

class LegacyStorefrontRedirectController extends Controller
{
    public function __construct(private StorefrontLocaleUrlService $urls) {}

    public function home(): RedirectResponse
    {
        return redirect()->route('shop.home', ['locale' => $this->urls->defaultLocale()]);
    }

    public function named(string $route, array $parameters = [], array $query = []): RedirectResponse
    {
        return redirect()->route($route, [
            'locale' => $this->urls->defaultLocale(),
            ...$parameters,
            ...$query,
        ]);
    }

    public function entity(string $type, string $key): RedirectResponse
    {
        $destination = $this->urls->legacyEntity($type, $key);
        abort_if($destination === null || $destination === route('shop.home', ['locale' => $this->urls->defaultLocale()]), 404);

        return redirect()->to($destination);
    }
}
