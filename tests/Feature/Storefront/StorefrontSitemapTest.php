<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Product;
use App\Services\StorefrontSeoService;
use DOMDocument;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_contains_only_eligible_localized_canonical_urls(): void
    {
        $category = Category::factory()->create(['status' => true]);
        $category->translations()->createMany([
            ['locale' => 'en', 'name' => 'Cameras', 'slug' => 'cameras'],
            ['locale' => 'ar', 'name' => 'كاميرات', 'slug' => 'cameras-ar'],
        ]);
        $hiddenCategory = Category::factory()->create(['status' => false]);
        $hiddenCategory->translations()->create(['locale' => 'en', 'name' => 'Hidden', 'slug' => 'hidden']);

        $product = $this->product('camera', 10);
        $this->product('free', 0);
        Product::factory()->create([
            'type' => ProductType::Simple->value,
            'status' => true,
            'is_visible_individually' => false,
            'price' => 10,
        ])->translations()->create(['locale' => 'en', 'name' => 'Hidden', 'url_key' => 'hidden']);

        $page = CmsPage::query()->create(['code' => 'about', 'is_active' => true, 'sort_order' => 0]);
        $page->translations()->create(['locale' => 'en', 'title' => 'About', 'slug' => 'about', 'body' => 'About']);
        $inactive = CmsPage::query()->create(['code' => 'inactive', 'is_active' => false, 'sort_order' => 1]);
        $inactive->translations()->create(['locale' => 'en', 'title' => 'Inactive', 'slug' => 'inactive', 'body' => 'No']);
        Cache::flush();

        $response = $this->get('/sitemap.xml');
        $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertFalse($response->headers->has('Set-Cookie'));

        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($response->getContent()));
        $locations = collect($document->getElementsByTagName('loc'))
            ->map(fn ($node): string => $node->textContent);

        $expected = [
            route('shop.home', ['locale' => 'en']),
            route('shop.products.index', ['locale' => 'en']),
            route('shop.categories.show', ['locale' => 'en', 'slug' => 'cameras']),
            route('shop.products.show', ['locale' => 'en', 'url_key' => 'camera-en']),
            route('shop.pages.show', ['locale' => 'en', 'slug' => 'about']),
            route('shop.home', ['locale' => 'ar']),
            route('shop.products.index', ['locale' => 'ar']),
            route('shop.categories.show', ['locale' => 'ar', 'slug' => 'cameras-ar']),
            route('shop.products.show', ['locale' => 'ar', 'url_key' => 'camera-ar']),
        ];
        $this->assertSame($expected, $locations->all());
        $this->assertSame($locations->count(), $locations->unique()->count());
        $locations->each(function (string $url): void {
            $this->assertNotFalse(filter_var($url, FILTER_VALIDATE_URL));
            $this->assertNull(parse_url($url, PHP_URL_QUERY));
            $this->assertNull(parse_url($url, PHP_URL_FRAGMENT));
        });
        $response->assertDontSee('cart')->assertDontSee('checkout')->assertDontSee('inactive');
    }

    public function test_out_of_stock_product_remains_eligible(): void
    {
        $product = $this->product('out-of-stock', 20);
        $product->inventory()->create(['quantity' => 0, 'average_cost' => 1]);

        $this->get('/sitemap.xml')->assertSee(route('shop.products.show', [
            'locale' => 'en',
            'url_key' => 'out-of-stock-en',
        ]), false);
    }

    public function test_sitemap_query_count_is_bounded_as_catalog_grows(): void
    {
        $this->product('initial', 10);
        $seo = app(StorefrontSeoService::class);
        $seo->sitemapUrls();
        $phase = 'small';
        $counts = ['small' => 0, 'large' => 0];
        DB::listen(function (QueryExecuted $query) use (&$phase, &$counts): void {
            if ($phase !== null) {
                $counts[$phase]++;
            }
        });

        $seo->sitemapUrls();
        $phase = null;
        foreach (range(1, 20) as $index) {
            $this->product('bulk-'.$index, 10);
        }
        $phase = 'large';
        $seo->sitemapUrls();

        $this->assertSame(3, $counts['small']);
        $this->assertSame($counts['small'], $counts['large']);
    }

    private function product(string $key, float $price): Product
    {
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
            'price' => $price,
        ]);
        $product->translations()->createMany([
            ['locale' => 'en', 'name' => $key, 'url_key' => $key.'-en'],
            ['locale' => 'ar', 'name' => $key, 'url_key' => $key.'-ar'],
        ]);

        return $product;
    }
}
