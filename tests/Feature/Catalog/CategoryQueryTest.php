<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_three_level_navigation_keeps_the_shared_hierarchy_at_two_queries(): void
    {
        $root = $this->category('Root');
        $child = $this->category('Child', $root);
        $this->category('Grandchild', $child);

        foreach (range(1, 12) as $index) {
            $extraRoot = $this->category('Root '.$index);
            $extraChild = $this->category('Child '.$index, $extraRoot);
            $this->category('Grandchild '.$index, $extraChild);
        }

        $categoryQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$categoryQueries): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'from "categories"')
                || str_contains($sql, 'from "category_translations"')
                || str_contains($sql, 'from `categories`')
                || str_contains($sql, 'from `category_translations`')) {
                $categoryQueries++;
            }
        });

        $this->view('shop.components.navbar')->assertSee('Grandchild');

        $this->assertSame(2, $categoryQueries);
    }

    private function category(string $name, ?Category $parent = null): Category
    {
        $category = Category::factory()->create([
            'parent_id' => $parent?->id,
            'level' => $parent ? $parent->level + 1 : 0,
        ]);
        $category->translations()->create([
            'locale' => 'en',
            'name' => $name,
            'slug' => str($name)->slug(),
        ]);

        return $category;
    }
}
