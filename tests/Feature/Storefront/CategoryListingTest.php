<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_localized_category_page_lists_products_from_its_active_branch(): void
    {
        $root = $this->category('Electronics', 'electronics');
        $child = $this->category('Phones', 'phones', $root);
        $grandchild = $this->category('Smartphones', 'smartphones', $child);
        $inactive = $this->category('Inactive', 'inactive', $root, false);
        $rootProduct = $this->product('Root Product', $root);
        $childProduct = $this->product('Child Product', $child);
        $grandchildProduct = $this->product('Grandchild Product', $grandchild);
        $this->product('Inactive Product', $inactive);
        $this->product('Outside Product', $this->category('Fashion', 'fashion'));

        $response = $this->get(route('shop.categories.show', 'electronics'))
            ->assertOk()
            ->assertSeeInOrder(['Electronics', 'Phones', 'Smartphones'])
            ->assertSee('aria-current="page"', false)
            ->assertSee('action="'.route('shop.categories.show', 'electronics').'"', false)
            ->assertSee('name="q"', false)
            ->assertDontSee('name="category"', false)
            ->assertDontSee('Inactive Product')
            ->assertDontSee('Outside Product');

        $this->assertEqualsCanonicalizing(
            [$rootProduct->id, $childProduct->id, $grandchildProduct->id],
            $response->viewData('products')->pluck('id')->all()
        );
    }

    public function test_category_requires_its_complete_active_current_locale_ancestor_chain(): void
    {
        $untranslatedRoot = Category::factory()->create(['status' => true]);
        $child = $this->category('Reachable Name Only', 'orphaned-slug', $untranslatedRoot);
        $inactiveRoot = $this->category('Inactive Root', 'inactive-root', active: false);
        $this->category('Inactive Descendant', 'inactive-descendant', $inactiveRoot);
        $arabic = $this->category('Arabic Category', 'arabic-category', locale: 'ar');

        $this->get(route('shop.categories.show', $child->translations->first()->slug))->assertNotFound();
        $this->get(route('shop.categories.show', 'inactive-descendant'))->assertNotFound();
        $this->get(route('shop.categories.show', $arabic->translations->first()->slug))->assertNotFound();
        $this->get(route('shop.categories.show', 'missing'))->assertNotFound();
    }

    public function test_category_filters_search_sort_and_conflicting_category_parameter_is_ignored(): void
    {
        $category = $this->category('Cameras', 'cameras');
        $other = $this->category('Other', 'other');
        $matching = $this->product('Alpha Camera', $category, 30, 'Professional camera');
        $this->product('Beta Camera', $category, 10);
        $this->product('Outside Camera', $other, 5, 'Professional camera');

        $products = $this->get(route('shop.categories.show', [
            'slug' => 'cameras',
            'category' => $other->id,
            'q' => 'professional',
            'min_price' => 20,
            'sort' => 'price_desc',
        ]))->assertOk()->viewData('products');

        $this->assertSame([$matching->id], $products->pluck('id')->all());
    }

    public function test_category_uses_seo_banner_and_pagination_canonical_rules(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('categories/banner.webp', 'banner');
        $category = $this->category('Books', 'books', state: ['banner_path' => 'categories/banner.webp']);
        $category->translations()->first()->update([
            'meta_title' => 'Books Meta',
            'meta_description' => 'Books Meta Description',
        ]);
        foreach (range(1, 13) as $index) {
            $this->product('Book '.$index, $category, 10 + $index);
        }

        $base = route('shop.categories.show', 'books');
        $this->get($base.'?page=2')->assertOk()
            ->assertSee('<title>Books Meta</title>', false)
            ->assertSee('content="Books Meta Description"', false)
            ->assertSee(Storage::disk('public')->url('categories/banner.webp'), false)
            ->assertSee('<link rel="canonical" href="'.$base.'?page=2">', false);
        $this->get($base.'?q=Book&page=2')->assertOk()
            ->assertSee('<link rel="canonical" href="'.$base.'">', false);
    }

    private function category(
        string $name,
        string $slug,
        ?Category $parent = null,
        bool $active = true,
        string $locale = 'en',
        array $state = []
    ): Category {
        $category = Category::factory()->create(array_merge([
            'parent_id' => $parent?->id,
            'level' => $parent ? $parent->level + 1 : 0,
            'status' => $active,
        ], $state));
        $category->translations()->create(compact('locale', 'name', 'slug'));

        return $category;
    }

    private function product(
        string $name,
        Category $category,
        float $price = 10,
        ?string $shortDescription = null
    ): Product {
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
            'url_key' => str($name)->slug().'-'.$product->id,
            'short_description' => $shortDescription,
        ]);
        $product->inventory()->create(['quantity' => 5, 'average_cost' => 1]);
        $product->categories()->attach($category);

        return $product;
    }
}
