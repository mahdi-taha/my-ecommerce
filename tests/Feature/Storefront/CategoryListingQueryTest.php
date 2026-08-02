<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryListingQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_category_page_reuses_one_two_query_hierarchy_as_depth_and_products_grow(): void
    {
        $root = $this->category('Root', 'root');
        $this->product('First', $root);
        $parent = $root;
        foreach (range(1, 8) as $index) {
            $parent = $this->category('Level '.$index, 'level-'.$index, $parent);
            $this->product('Product '.$index, $parent);
        }
        $catalogQueries = 0;
        $hierarchyRootQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$catalogQueries, &$hierarchyRootQueries): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'categories') || str_contains($sql, 'products')) {
                $catalogQueries++;
            }
            if (str_starts_with($sql, 'select * from "categories" where "status"')) {
                $hierarchyRootQueries++;
            }
        });

        $this->get(route('shop.categories.show', 'root'))->assertOk();

        $this->assertSame(1, $hierarchyRootQueries);
        $this->assertLessThanOrEqual(20, $catalogQueries);
    }

    private function category(string $name, string $slug, ?Category $parent = null): Category
    {
        $category = Category::factory()->create([
            'parent_id' => $parent?->id,
            'level' => $parent ? $parent->level + 1 : 0,
        ]);
        $category->translations()->create(['locale' => 'en', 'name' => $name, 'slug' => $slug]);

        return $category;
    }

    private function product(string $name, Category $category): void
    {
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'price' => 10,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $product->translations()->create([
            'locale' => 'en',
            'name' => $name,
            'url_key' => str($name)->slug().'-'.$product->id,
        ]);
        $product->inventory()->create(['quantity' => 5, 'average_cost' => 1]);
        $product->categories()->attach($category);
    }
}
