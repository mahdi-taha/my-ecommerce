<?php

namespace App\Http\Controllers;

use App\Services\StorefrontLocaleUrlService;
use App\Services\StorefrontSeoService;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $disallowed = [
            '/admin',
            ...collect(StorefrontLocaleUrlService::LOCALES)->flatMap(fn (string $locale): array => [
                "/{$locale}/cart",
                "/{$locale}/checkout",
                "/{$locale}/wishlist",
                "/{$locale}/account",
                "/{$locale}/login",
                "/{$locale}/register",
                "/{$locale}/forgot-password",
                "/{$locale}/reset-password",
            ])->all(),
        ];
        $lines = ['User-agent: *'];
        foreach ($disallowed as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = 'Sitemap: '.route('seo.sitemap');

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function sitemap(StorefrontSeoService $seo): Response
    {
        return response()->view('seo.sitemap', [
            'urls' => $seo->sitemapUrls(),
        ], 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
