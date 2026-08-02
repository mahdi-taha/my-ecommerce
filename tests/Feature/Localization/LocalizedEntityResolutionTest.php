<?php

namespace Tests\Feature\Localization;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizedEntityResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_product_category_and_cms_resolution_is_strict_to_url_locale(): void
    {
        $product = Product::factory()->create(['type' => ProductType::Simple->value, 'price' => 10]);
        $product->translations()->createMany([
            ['locale' => 'en', 'name' => 'Camera', 'url_key' => 'camera'],
            ['locale' => 'ar', 'name' => 'Arabic Camera', 'url_key' => 'camera-ar'],
        ]);
        $product->inventory()->create(['quantity' => 1, 'average_cost' => 1]);
        $category = Category::factory()->create(['status' => true]);
        $category->translations()->createMany([
            ['locale' => 'en', 'name' => 'Phones', 'slug' => 'phones'],
            ['locale' => 'ar', 'name' => 'Arabic Phones', 'slug' => 'phones-ar'],
        ]);
        $page = CmsPage::query()->create(['code' => 'about', 'is_active' => true, 'sort_order' => 0]);
        $page->translations()->createMany([
            ['locale' => 'en', 'title' => 'About', 'slug' => 'about', 'body' => 'Body'],
            ['locale' => 'ar', 'title' => 'Arabic About', 'slug' => 'about-ar', 'body' => 'Body'],
        ]);

        $this->get('/en/products/camera')->assertOk();
        $this->get('/ar/products/camera-ar')->assertOk();
        $this->get('/ar/products/camera')->assertNotFound();
        $this->get('/en/categories/phones')->assertOk();
        $this->get('/ar/categories/phones')->assertNotFound();
        $this->get('/en/pages/about')->assertOk();
        $this->get('/ar/pages/about')->assertNotFound();
    }

    public function test_locale_switch_uses_stable_product_identity(): void
    {
        $product = Product::factory()->create(['type' => ProductType::Simple->value, 'price' => 10]);
        $product->translations()->createMany([
            ['locale' => 'en', 'name' => 'Camera', 'url_key' => 'camera'],
            ['locale' => 'ar', 'name' => 'Arabic Camera', 'url_key' => 'camera-ar'],
        ]);
        $product->inventory()->create(['quantity' => 1, 'average_cost' => 1]);

        $this->get('/en/products/camera')->assertOk();
        $this->post('/en/locale/ar')->assertRedirect('/ar/products/camera-ar');

        $product->translations()->where('locale', 'ar')->delete();
        $this->get('/en/products/camera')->assertOk();
        $this->post('/en/locale/ar')->assertRedirect('/ar');
    }

    public function test_locale_switch_preserves_reset_token_and_email(): void
    {
        $this->get('/ar/reset-password/reset-token?email=customer%40example.test')->assertOk();

        $this->post('/ar/locale/en')
            ->assertRedirect('/en/reset-password/reset-token?email=customer%40example.test');
    }
}
