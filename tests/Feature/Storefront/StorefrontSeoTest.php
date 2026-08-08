<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductType;
use App\Models\CmsPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StorefrontSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_homepage_has_localized_description_canonical_and_open_graph_metadata(): void
    {
        $this->get(route('shop.home'))->assertOk()
            ->assertSee('<meta name="description" content="'.__('shop.home.meta_description').'">', false)
            ->assertSee('<link rel="canonical" href="'.route('shop.home').'">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:url" content="'.route('shop.home').'">', false)
            ->assertDontSee('name="keywords"', false);
    }

    public function test_product_metadata_uses_localized_overrides_and_fallbacks(): void
    {
        $product = $this->product('camera', 'Camera', 'Camera summary');
        $product->translations()->where('locale', 'en')->update([
            'meta_title' => 'Camera SEO Title',
            'meta_description' => 'Camera SEO Description',
        ]);

        $this->get(route('shop.products.show', 'camera-en'))->assertOk()
            ->assertSee('<title>Camera SEO Title</title>', false)
            ->assertSee('<meta name="description" content="Camera SEO Description">', false)
            ->assertSee('<link rel="canonical" href="'.route('shop.products.show', 'camera-en').'">', false)
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertDontSee('name="keywords"', false);

        $product->translations()->where('locale', 'en')->update([
            'meta_title' => null,
            'meta_description' => null,
        ]);

        $this->get(route('shop.products.show', 'camera-en'))->assertOk()
            ->assertSee('<title>Camera</title>', false)
            ->assertSee('<meta name="description" content="Camera summary">', false);
    }

    public function test_shop_canonical_preserves_only_unfiltered_pagination(): void
    {
        foreach (range(1, 13) as $index) {
            $this->product('product-'.$index, 'Product '.$index, 'Summary');
        }

        $base = route('shop.products.index');
        $this->get($base.'?page=2')->assertOk()
            ->assertSee('<link rel="canonical" href="'.$base.'?page=2">', false);
        $this->get($base.'?q=Product&page=2')->assertOk()
            ->assertSee('<link rel="canonical" href="'.$base.'">', false);
    }

    public function test_top_selling_has_localized_metadata_and_clean_canonicals(): void
    {
        $base = route('shop.products.top-selling', ['locale' => 'en']);
        $this->get($base)->assertOk()
            ->assertSee('<title>'.__('shop.listing.top_selling.title').'</title>', false)
            ->assertSee('<meta name="description" content="'.__('shop.listing.top_selling.meta_description').'">', false)
            ->assertSee('<link rel="canonical" href="'.$base.'">', false);
        $this->get($base.'?page=2')->assertOk()
            ->assertSee('<link rel="canonical" href="'.$base.'?page=2">', false);
        $this->get($base.'?q=camera&page=2')->assertOk()
            ->assertSee('<link rel="canonical" href="'.$base.'">', false);

        $this->get(route('shop.products.top-selling', ['locale' => 'ar']))->assertOk()
            ->assertSee('<title>'.__('shop.listing.top_selling.title', [], 'ar').'</title>', false);
    }

    public function test_product_locale_switch_uses_translated_key_and_missing_translation_returns_home(): void
    {
        $this->product('camera', 'Camera', 'Summary', withArabic: true);

        $this->get(route('shop.products.show', ['locale' => 'en', 'url_key' => 'camera-en']).'?source=product')->assertOk();
        $this->post(route('shop.locale.update', ['locale' => 'en', 'targetLocale' => 'ar']))
            ->assertRedirect(route('shop.products.show', ['locale' => 'ar', 'url_key' => 'camera-ar']).'?source=product');

        $this->product('english-only', 'English Only', 'Summary');
        $this->get(route('shop.products.show', ['locale' => 'en', 'url_key' => 'english-only-en']))->assertOk();
        $this->post(route('shop.locale.update', ['locale' => 'en', 'targetLocale' => 'ar']))
            ->assertRedirect(route('shop.home', ['locale' => 'ar']));
    }

    public function test_cms_page_with_empty_description_omits_description_and_keeps_open_graph(): void
    {
        $page = CmsPage::query()->create(['code' => 'about', 'is_active' => true, 'sort_order' => 0]);
        $page->translations()->create([
            'locale' => 'en',
            'title' => 'About',
            'slug' => 'about',
            'body' => 'About body.',
            'meta_description' => '   ',
        ]);
        Cache::flush();

        $this->get(route('shop.pages.show', 'about'))->assertOk()
            ->assertDontSee('<meta name="description"', false)
            ->assertSee('<link rel="canonical" href="'.route('shop.pages.show', 'about').'">', false)
            ->assertSee('<meta property="og:type" content="website">', false);
    }

    private function product(
        string $key,
        string $name,
        string $shortDescription,
        bool $withArabic = false,
    ): Product {
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'price' => 10,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $translations = [[
            'locale' => 'en',
            'name' => $name,
            'url_key' => $key.'-en',
            'short_description' => $shortDescription,
        ]];
        if ($withArabic) {
            $translations[] = [
                'locale' => 'ar',
                'name' => 'Arabic '.$name,
                'url_key' => $key.'-ar',
                'short_description' => 'Arabic summary',
            ];
        }
        $product->translations()->createMany($translations);
        $product->inventory()->create(['quantity' => 5, 'average_cost' => 1]);

        return $product;
    }
}
