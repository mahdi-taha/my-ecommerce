<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_reparenting_recalculates_every_descendant_level(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id, 'level' => 1]);
        $grandchild = Category::factory()->create(['parent_id' => $child->id, 'level' => 2]);
        app(CategoryService::class)->update($child, $this->data(['parent_id' => null]));

        $this->assertSame(0, $child->fresh()->level);
        $this->assertSame(1, $grandchild->fresh()->level);
    }

    public function test_category_cannot_be_moved_beneath_its_descendant(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id, 'level' => 1]);
        $this->expectException(ValidationException::class);
        app(CategoryService::class)->update($root, $this->data(['parent_id' => $child->id]));
    }

    public function test_unused_leaf_category_can_be_deleted_with_owned_records_and_image_cleanup(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('categories/logos/delete.png', 'logo');
        Storage::disk('public')->put('categories/banners/delete.png', 'banner');
        $category = Category::factory()->create([
            'logo_path' => 'categories/logos/delete.png',
            'banner_path' => 'categories/banners/delete.png',
        ]);
        $translation = $category->translations()->create([
            'locale' => 'en', 'name' => 'Delete', 'slug' => 'delete',
        ]);

        $response = $this
            ->actingAs(User::factory()->create(), 'admin')
            ->deleteJson(route('admin.categories.destroy', $category));

        $response->assertOk()->assertJson(['message' => 'Category deleted successfully.']);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('category_translations', ['id' => $translation->id]);
        Storage::disk('public')->assertMissing('categories/logos/delete.png');
        Storage::disk('public')->assertMissing('categories/banners/delete.png');
    }

    public function test_category_with_children_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Category::factory()->create(['parent_id' => $category->id, 'level' => 1]);

        $this->assertCategoryDeletionRejected(
            $category,
            'A category with child categories cannot be deleted.'
        );
    }

    public function test_category_containing_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        $category->products()->attach(Product::factory()->create());

        $this->assertCategoryDeletionRejected(
            $category,
            'A category containing products cannot be deleted.'
        );
    }

    private function assertCategoryDeletionRejected(Category $category, string $message): void
    {
        try {
            app(CategoryService::class)->delete($category);
            $this->fail('A referenced category was deleted.');
        } catch (ValidationException $exception) {
            $this->assertSame($message, $exception->errors()['category'][0]);
        }

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    private function data(array $overrides = []): array
    {
        return array_merge(['parent_id' => null, 'position' => 0, 'status' => true,
            'category_name_en' => 'Category', 'category_slug_en' => 'category-'.uniqid(),
            'category_name_ar' => 'فئة', 'category_slug_ar' => 'category-ar-'.uniqid(),
            'filterable_attributes' => []], $overrides);
    }
}
