<?php

namespace Tests\Feature\Localization;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizedStorefrontRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_get_pages_redirect_temporarily_to_default_locale(): void
    {
        $this->get('/')->assertRedirect('/en')->assertStatus(302);
        $this->get('/shop')->assertRedirect('/en/shop')->assertStatus(302);
        $this->get('/cart')->assertRedirect('/en/cart')->assertStatus(302);
        $this->get('/reset-password/reset-token?email=customer%40example.test')
            ->assertRedirect('/en/reset-password/reset-token?email=customer%40example.test')
            ->assertStatus(302);
    }

    public function test_resolvable_legacy_entity_redirects_and_unknown_entity_is_not_found(): void
    {
        $product = Product::factory()->create(['type' => ProductType::Simple->value, 'price' => 10]);
        $product->translations()->create(['locale' => 'en', 'name' => 'Camera', 'url_key' => 'camera']);

        $this->get('/products/camera')->assertRedirect('/en/products/camera')->assertStatus(302);
        $this->get('/products/missing')->assertNotFound();
    }

    public function test_legacy_mutation_endpoints_do_not_exist(): void
    {
        $this->post('/cart/items')->assertNotFound();
        $this->post('/login')->assertMethodNotAllowed();
    }
}
