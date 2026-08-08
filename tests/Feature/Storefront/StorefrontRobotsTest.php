<?php

namespace Tests\Feature\Storefront;

use Tests\TestCase;

class StorefrontRobotsTest extends TestCase
{
    public function test_robots_is_deterministic_plain_text_and_stateless(): void
    {
        $expected = implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /en/cart',
            'Disallow: /en/checkout',
            'Disallow: /en/wishlist',
            'Disallow: /en/account',
            'Disallow: /en/login',
            'Disallow: /en/register',
            'Disallow: /en/forgot-password',
            'Disallow: /en/reset-password',
            'Disallow: /ar/cart',
            'Disallow: /ar/checkout',
            'Disallow: /ar/wishlist',
            'Disallow: /ar/account',
            'Disallow: /ar/login',
            'Disallow: /ar/register',
            'Disallow: /ar/forgot-password',
            'Disallow: /ar/reset-password',
            'Sitemap: '.route('seo.sitemap'),
            '',
        ]);

        $first = $this->get('/robots.txt');
        $first->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame($expected, $first->getContent());
        $this->assertFalse($first->headers->has('Set-Cookie'));
        $this->assertSame($first->getContent(), $this->get('/robots.txt')->getContent());
    }
}
