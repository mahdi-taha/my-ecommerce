<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_uploaded_image_becomes_the_only_base_image(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create(['sku' => 'IMAGE-1']);
        foreach (['en', 'ar'] as $locale) {
            $product->translations()->create(['locale' => $locale, 'name' => 'Image', 'url_key' => 'image-'.$locale]);
        }

        app(ProductService::class)->update($product, $this->data([
            'new_images' => [UploadedFile::fake()->image('first.jpg'), UploadedFile::fake()->image('second.jpg')],
            'new_image_sort_orders' => [2, 1],
        ]));

        $this->assertSame(1, $product->images()->where('is_base', true)->count());
        $this->assertTrue($product->images()->orderBy('sort_order')->firstOrFail()->is_base);
    }

    private function data(array $overrides = []): array
    {
        return array_merge(['sku' => 'IMAGE-1', 'product_number' => null, 'product_name_en' => 'Image',
            'product_name_ar' => 'صورة', 'url_key_en' => 'image-en', 'url_key_ar' => 'image-ar',
            'price' => 10, 'special_price' => null, 'is_new' => false,
            'is_featured' => false, 'is_visible_individually' => true, 'status' => true,
            'category_ids' => [], 'attributes' => []], $overrides);
    }
}
