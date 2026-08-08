<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StorefrontHreflangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_home_and_clean_shop_pagination_have_localized_alternates(): void
    {
        $this->get(route('shop.home', ['locale' => 'en']))->assertOk()
            ->assertSee($this->alternate('en', route('shop.home', ['locale' => 'en'])), false)
            ->assertSee($this->alternate('ar', route('shop.home', ['locale' => 'ar'])), false)
            ->assertSee($this->alternate('x-default', route('shop.home', ['locale' => 'en'])), false);

        $this->get(route('shop.products.index', ['locale' => 'en', 'page' => 2]))->assertOk()
            ->assertSee($this->alternate('ar', route('shop.products.index', ['locale' => 'ar', 'page' => 2])), false);
    }

    public function test_product_alternates_preserve_identity_and_omit_missing_translation(): void
    {
        $this->product('camera', true);
        $this->get(route('shop.products.show', ['locale' => 'en', 'url_key' => 'camera-en']))->assertOk()
            ->assertSee($this->alternate('en', route('shop.products.show', ['locale' => 'en', 'url_key' => 'camera-en'])), false)
            ->assertSee($this->alternate('ar', route('shop.products.show', ['locale' => 'ar', 'url_key' => 'camera-ar'])), false);

        $this->product('english-only', false);
        $response = $this->get(route('shop.products.show', ['locale' => 'en', 'url_key' => 'english-only-en']))->assertOk();
        $response->assertSee($this->alternate('en', route('shop.products.show', ['locale' => 'en', 'url_key' => 'english-only-en'])), false)
            ->assertDontSee('hreflang="ar"', false)
            ->assertDontSee($this->alternate('ar', route('shop.home', ['locale' => 'ar'])), false);
    }

    public function test_category_and_cms_alternates_preserve_entity_identity(): void
    {
        $category = Category::factory()->create(['status' => true]);
        $category->translations()->createMany([
            ['locale' => 'en', 'name' => 'Cameras', 'slug' => 'cameras'],
            ['locale' => 'ar', 'name' => 'كاميرات', 'slug' => 'cameras-ar'],
        ]);
        $page = CmsPage::query()->create(['code' => 'about', 'is_active' => true, 'sort_order' => 0]);
        $page->translations()->createMany([
            ['locale' => 'en', 'title' => 'About', 'slug' => 'about', 'body' => 'About'],
            ['locale' => 'ar', 'title' => 'حول', 'slug' => 'about-ar', 'body' => 'حول'],
        ]);
        Cache::flush();

        $this->get(route('shop.categories.show', ['locale' => 'en', 'slug' => 'cameras']))->assertOk()
            ->assertSee($this->alternate('ar', route('shop.categories.show', ['locale' => 'ar', 'slug' => 'cameras-ar'])), false);
        $this->get(route('shop.pages.show', ['locale' => 'en', 'slug' => 'about']))->assertOk()
            ->assertSee($this->alternate('ar', route('shop.pages.show', ['locale' => 'ar', 'slug' => 'about-ar'])), false);
    }

    public function test_filtered_listing_alternates_never_carry_filter_parameters(): void
    {
        $response = $this->get(route('shop.products.index', [
            'locale' => 'en',
            'q' => 'camera',
            'sort' => 'name_desc',
            'page' => 2,
        ]))->assertOk();

        $response->assertSee($this->alternate('ar', route('shop.products.index', ['locale' => 'ar'])), false)
            ->assertDontSee('hreflang="ar" href="'.route('shop.products.index', ['locale' => 'ar']).'?', false);
    }

    private function product(string $key, bool $withArabic): Product
    {
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'price' => 10,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $translations = [['locale' => 'en', 'name' => $key, 'url_key' => $key.'-en']];
        if ($withArabic) {
            $translations[] = ['locale' => 'ar', 'name' => $key, 'url_key' => $key.'-ar'];
        }
        $product->translations()->createMany($translations);

        return $product;
    }

    private function alternate(string $locale, string $url): string
    {
        return '<link rel="alternate" hreflang="'.$locale.'" href="'.$url.'">';
    }
}
