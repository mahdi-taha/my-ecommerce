<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_parent_and_configurable_parent_deletion_are_restricted(): void
    {
        $category = Category::factory()->create();
        Category::factory()->create(['parent_id' => $category->id, 'level' => 1]);
        try {
            $category->delete();
            $this->fail('Expected category parent deletion to be restricted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('categories', ['id' => $category->id]);
        }

        $parent = Product::factory()->create(['type' => 'configurable']);
        Product::factory()->create(['configurable_id' => $parent->id]);
        $this->expectException(QueryException::class);
        $parent->delete();
    }
}
