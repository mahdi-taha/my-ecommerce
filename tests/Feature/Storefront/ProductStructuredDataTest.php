<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductReviewStatus;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStructuredDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_simple_product_uses_offer_and_only_approved_review_aggregates(): void
    {
        $product = $this->product('simple-product', 'Simple Product', 25);
        $product->inventory()->create(['quantity' => 3, 'average_cost' => 5]);
        $customer = User::factory()->customer()->create();
        ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $customer->id,
            'rating' => 4,
            'review' => 'Approved review.',
            'status' => ProductReviewStatus::Approved,
        ]);
        ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => User::factory()->customer()->create()->id,
            'rating' => 1,
            'review' => 'Pending review.',
            'status' => ProductReviewStatus::Pending,
        ]);

        $data = $this->structuredData(
            $this->get(route('shop.products.show', 'simple-product'))->assertOk()->getContent()
        );

        $this->assertSame('Product', $data['@type']);
        $this->assertSame('Offer', $data['offers']['@type']);
        $this->assertSame('25.0000', $data['offers']['price']);
        $this->assertSame('https://schema.org/InStock', $data['offers']['availability']);
        $this->assertSame(1, $data['aggregateRating']['reviewCount']);
        $this->assertSame('4.0', $data['aggregateRating']['ratingValue']);
        $this->assertArrayNotHasKey('review', $data);
    }

    public function test_simple_product_without_approved_reviews_has_no_aggregate_rating(): void
    {
        $product = $this->product('unreviewed', 'Unreviewed', 15);
        $product->inventory()->create(['quantity' => 0, 'average_cost' => 5]);

        $data = $this->structuredData(
            $this->get(route('shop.products.show', 'unreviewed'))->assertOk()->getContent()
        );

        $this->assertSame('OutOfStock', basename($data['offers']['availability']));
        $this->assertArrayNotHasKey('aggregateRating', $data);
    }

    public function test_configurable_product_uses_the_resolved_display_range_and_variant_availability(): void
    {
        $attribute = Attribute::factory()->create([
            'type' => 'select',
            'is_configurable' => true,
            'is_active' => true,
        ]);
        $attribute->translations()->create(['locale' => 'en', 'admin_name' => 'Color']);
        $red = $this->option($attribute, 'red', 'Red');
        $blue = $this->option($attribute, 'blue', 'Blue');
        $parent = Product::factory()->create([
            'type' => ProductType::Configurable->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $parent->translations()->create([
            'locale' => 'en',
            'name' => 'Configurable Product',
            'url_key' => 'configurable-product',
            'short_description' => 'Configurable summary.',
        ]);
        $superAttribute = $parent->superAttributes()->create(['attribute_id' => $attribute->id]);
        $superAttribute->options()->sync([$red->id, $blue->id]);
        $this->variant($parent, $attribute, $red, 10, 0);
        $this->variant($parent, $attribute, $blue, 20, 4);

        $response = $this->get(route('shop.products.show', 'configurable-product'))->assertOk();
        $data = $this->structuredData($response->getContent());

        $this->assertSame('AggregateOffer', $data['offers']['@type']);
        $this->assertSame('10.0000', $data['offers']['lowPrice']);
        $this->assertSame('20.0000', $data['offers']['highPrice']);
        $this->assertSame(2, $data['offers']['offerCount']);
        $this->assertSame('https://schema.org/InStock', $data['offers']['availability']);
        $this->assertSame(10.0, $response->viewData('configurablePriceRange')['minimum']);
        $this->assertSame(20.0, $response->viewData('configurablePriceRange')['maximum']);
    }

    private function product(string $key, string $name, float $price): Product
    {
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'price' => $price,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $product->translations()->create([
            'locale' => 'en',
            'name' => $name,
            'url_key' => $key,
            'short_description' => $name.' summary.',
        ]);

        return $product;
    }

    private function option(Attribute $attribute, string $code, string $label): AttributeOption
    {
        $option = $attribute->options()->create(['code' => $code, 'sort_order' => 0]);
        $option->translations()->create(['locale' => 'en', 'label' => $label]);

        return $option;
    }

    private function variant(
        Product $parent,
        Attribute $attribute,
        AttributeOption $option,
        float $price,
        int $quantity,
    ): void {
        $variant = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => $parent->id,
            'price' => $price,
            'status' => true,
            'is_visible_individually' => false,
        ]);
        $variant->attributeValues()->create([
            'attribute_id' => $attribute->id,
            'attribute_option_id' => $option->id,
        ]);
        $variant->inventory()->create(['quantity' => $quantity, 'average_cost' => 1]);
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
