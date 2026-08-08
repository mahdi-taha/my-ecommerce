<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\User;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryHierarchyValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_level_two_and_level_three_categories_are_created_with_zero_based_levels(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory($root);
        $grandchild = $this->createCategory($child);

        $this->assertSame(0, $root->level);
        $this->assertSame(1, $child->level);
        $this->assertSame(2, $grandchild->level);
    }

    public function test_category_cannot_be_created_beneath_stored_level_two(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory($root);
        $grandchild = $this->createCategory($child);

        $this->expectMaximumLevelValidation();

        $this->createCategory($grandchild);
    }

    public function test_leaf_cannot_be_moved_beneath_stored_level_two(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory($root);
        $grandchild = $this->createCategory($child);
        $moving = $this->createCategory();

        $this->expectMaximumLevelValidation();

        app(CategoryService::class)->update($moving, $this->data($grandchild));
    }

    public function test_subtree_move_is_rejected_when_its_deepest_descendant_would_exceed_level_two(): void
    {
        $destinationRoot = $this->createCategory();
        $destinationChild = $this->createCategory($destinationRoot);
        $movingRoot = $this->createCategory();
        $movingChild = $this->createCategory($movingRoot);

        try {
            app(CategoryService::class)->update($movingRoot, $this->data($destinationChild));
            $this->fail('An over-depth subtree was moved.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Categories can only have three hierarchy levels.',
                $exception->errors()['parent_id'][0]
            );
        }

        $this->assertNull($movingRoot->fresh()->parent_id);
        $this->assertSame(0, $movingRoot->fresh()->level);
        $this->assertSame(1, $movingChild->fresh()->level);
    }

    public function test_moving_a_subtree_recalculates_every_level_transactionally(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory($root);
        $grandchild = $this->createCategory($child);

        app(CategoryService::class)->update($child, $this->data());

        $this->assertNull($child->fresh()->parent_id);
        $this->assertSame(0, $child->fresh()->level);
        $this->assertSame(1, $grandchild->fresh()->level);
    }

    public function test_admin_parent_choices_hide_level_three_categories(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory($root);
        $grandchild = $this->createCategory($child);

        $response = $this->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.categories.create'));

        $response->assertOk()
            ->assertSee('value="'.$root->id.'"', false)
            ->assertSee('value="'.$child->id.'"', false)
            ->assertDontSee('value="'.$grandchild->id.'"', false);
    }

    private function createCategory(?Category $parent = null): Category
    {
        return app(CategoryService::class)->create($this->data($parent));
    }

    private function data(?Category $parent = null): array
    {
        $key = uniqid();

        return [
            'parent_id' => $parent?->id,
            'position' => 0,
            'status' => true,
            'category_name_en' => 'Category '.$key,
            'category_slug_en' => 'category-'.$key,
            'category_name_ar' => 'فئة '.$key,
            'category_slug_ar' => 'category-ar-'.$key,
            'filterable_attributes' => [],
        ];
    }

    private function expectMaximumLevelValidation(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Categories can only have three hierarchy levels.');
    }
}
