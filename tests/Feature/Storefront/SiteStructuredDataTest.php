<?php

namespace Tests\Feature\Storefront;

use App\Models\Setting;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteStructuredDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_home_reuses_store_identity_for_organization_and_website_graph(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('store/logo.png', 'logo');
        foreach ([
            'store_name' => 'Example Store',
            'store_logo_path' => 'store/logo.png',
            'store_email' => 'hello@example.test',
            'store_phone' => '+9611000000',
            'store_address' => 'Beirut, Lebanon',
            'facebook_url' => 'https://facebook.com/example',
            'whatsapp_url' => '',
            'instagram_url' => 'https://instagram.com/example',
        ] as $key => $value) {
            Setting::query()->updateOrCreate(['group' => 'store', 'key' => $key], [
                'value' => $value,
                'type' => 'string',
            ]);
        }
        Cache::flush();
        $settingQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$settingQueries): void {
            if (str_contains(strtolower($query->sql), 'from "settings"')) {
                $settingQueries++;
            }
        });

        $html = $this->get(route('shop.home', ['locale' => 'en']))->assertOk()->getContent();
        $data = $this->structuredData($html);
        $organization = $data['@graph'][0];
        $website = $data['@graph'][1];

        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('Organization', $organization['@type']);
        $this->assertSame('Example Store', $organization['name']);
        $this->assertSame(Storage::disk('public')->url('store/logo.png'), $organization['logo']);
        $this->assertSame(['https://facebook.com/example', 'https://instagram.com/example'], $organization['sameAs']);
        $this->assertSame('WebSite', $website['@type']);
        $this->assertSame(route('shop.home', ['locale' => 'en']), $website['url']);
        $this->assertSame('en', $website['inLanguage']);
        $this->assertSame($organization['@id'], $website['publisher']['@id']);
        $this->assertLessThanOrEqual(12, $settingQueries);
    }

    public function test_missing_optional_identity_values_are_omitted_and_graph_is_home_only(): void
    {
        Cache::flush();
        $data = $this->structuredData(
            $this->get(route('shop.home', ['locale' => 'ar']))->assertOk()->getContent()
        );
        $organization = $data['@graph'][0];

        $this->assertArrayNotHasKey('logo', $organization);
        $this->assertArrayNotHasKey('sameAs', $organization);
        $this->assertSame('ar', $data['@graph'][1]['inLanguage']);
        $this->get(route('shop.products.index', ['locale' => 'en']))->assertOk()
            ->assertDontSee('/#website', false);
    }

    /** @return array<string, mixed> */
    private function structuredData(string $html): array
    {
        $matched = preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $this->assertSame(1, $matched);
        $decoded = json_decode(trim($matches[1]), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
