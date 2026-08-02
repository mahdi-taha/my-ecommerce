<?php

namespace Tests\Feature\Cms;

use App\Rules\SafeStorefrontContentUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SafeStorefrontContentUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_allows_public_get_routes_and_https_but_rejects_unsafe_routes(): void
    {
        $rule = new SafeStorefrontContentUrl;
        foreach (['/shop?sort=newest', '/pages/about-us', 'https://example.com/path'] as $url) {
            $this->assertFalse(Validator::make(['url' => $url], ['url' => [$rule]])->fails(), $url);
        }foreach (['/admin/reviews', '/checkout', 'javascript:alert(1)', '//example.com'] as $url) {
            $this->assertTrue(Validator::make(['url' => $url], ['url' => [$rule]])->fails(), $url);
        }
    }
}
