<?php

namespace Tests\Feature\Catalog;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConfigurableProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_uses_option_codes_and_creates_no_inventory(): void
    {
        $attribute = Attribute::factory()->create(['type' => 'select', 'is_configurable' => true]);
        $red = $attribute->options()->create(['code' => 'red', 'sort_order' => 0]);
        $blue = $attribute->options()->create(['code' => 'blue', 'sort_order' => 1]);
        $parent = Product::factory()->create(['type' => 'configurable', 'sku' => 'SHIRT', 'price' => 25]);

        app(ProductService::class)->generateVariants($parent, [$attribute->id => [$red->id, $blue->id]]);

        $this->assertEqualsCanonicalizing(['SHIRT-red', 'SHIRT-blue'], $parent->variants()->pluck('sku')->all());
        $this->assertSame(0, $parent->variants()->whereHas('inventory')->count());
        $this->assertTrue($parent->variants()->get()->every(fn (Product $variant) => ! $variant->is_visible_individually));
    }

    public function test_variant_price_inputs_reject_more_than_four_decimal_places(): void
    {
        [$parent] = $this->configuredProduct();
        $variant = $parent->variants()->firstOrFail();
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.products.variants.update', [$parent, $variant]), [
                'sku' => $variant->sku,
                'price' => '10.12345',
                'special_price' => '9.12345',
                'status' => true,
            ])
            ->assertSessionHasErrors(['price', 'special_price']);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.products.variants.bulk-update', $parent), [
                'action' => 'prices',
                'variant_ids' => [$variant->id],
                'variants' => [
                    $variant->id => [
                        'price' => '10.12345',
                        'special_price' => '9.12345',
                    ],
                ],
            ])
            ->assertSessionHasErrors([
                "variants.{$variant->id}.price",
                "variants.{$variant->id}.special_price",
            ]);
    }

    public function test_add_variant_page_shows_all_options_of_assigned_attributes_only(): void
    {
        [$parent, $color, $red, $black, $blue] = $this->configuredProduct();
        $gender = Attribute::factory()->create(['type' => 'select', 'is_configurable' => true]);
        $gender->translations()->create(['locale' => 'en', 'admin_name' => 'Gender']);
        $man = $gender->options()->create(['code' => 'man', 'sort_order' => 0]);
        $man->translations()->create(['locale' => 'en', 'label' => 'UNASSIGNED_GENDER_OPTION']);

        $response = $this
            ->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.products.variants.index', $parent));

        $response
            ->assertOk()
            ->assertSee('Color')
            ->assertSee('Red')
            ->assertSee('Black')
            ->assertSee('Blue')
            ->assertDontSee('Gender')
            ->assertDontSee('UNASSIGNED_GENDER_OPTION')
            ->assertDontSee('name="options['.$gender->id.']"', false);
        $this->assertTrue($parent->superAttributes()->where('attribute_id', $color->id)->exists());
        $this->assertEqualsCanonicalizing(
            [$red->id, $black->id],
            $parent->superAttributes()->firstOrFail()->options()->pluck('attribute_options.id')->all()
        );
        $this->assertFalse($parent->superAttributes()->where('attribute_id', $gender->id)->exists());
    }

    public function test_previously_unused_option_creates_one_variant_and_is_attached_without_changing_existing_variants(): void
    {
        [$parent, $color, $red, $black, $blue] = $this->configuredProduct();
        $existingVariantIds = $parent->variants()->pluck('id')->all();

        $variant = app(ProductService::class)->createMissingVariant($parent, [
            $color->id => $blue->id,
        ]);

        $this->assertSame($parent->id, $variant->configurable_id);
        $this->assertSame($blue->id, $variant->attributeValues()->sole()->attribute_option_id);
        $this->assertEqualsCanonicalizing(
            $existingVariantIds,
            $parent->variants()->whereKeyNot($variant->id)->pluck('id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$red->id, $black->id, $blue->id],
            $parent->superAttributes()->firstOrFail()->options()->pluck('attribute_options.id')->all()
        );
    }

    public function test_duplicate_missing_variant_combination_remains_rejected(): void
    {
        [$parent, $color, $red] = $this->configuredProduct();

        try {
            app(ProductService::class)->createMissingVariant($parent, [
                $color->id => $red->id,
            ]);
            $this->fail('A duplicate variant combination was created.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This variant combination already exists.',
                $exception->errors()['options'][0]
            );
        }

        $this->assertSame(2, $parent->variants()->count());
    }

    public function test_add_variant_page_still_shows_every_attribute_option_after_attaching_a_new_one(): void
    {
        [$parent, $color, $red, $black, $blue] = $this->configuredProduct();
        app(ProductService::class)->createMissingVariant($parent, [
            $color->id => $blue->id,
        ]);

        $response = $this
            ->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.products.variants.index', $parent));

        $response
            ->assertOk()
            ->assertSee('Red')
            ->assertSee('Black')
            ->assertSee('Blue');
        $this->assertEqualsCanonicalizing(
            [$red->id, $black->id, $blue->id],
            $parent->superAttributes()->firstOrFail()->options()->pluck('attribute_options.id')->all()
        );
    }

    private function configuredProduct(): array
    {
        $color = Attribute::factory()->create(['type' => 'select', 'is_configurable' => true]);
        $color->translations()->create(['locale' => 'en', 'admin_name' => 'Color']);
        $red = $this->option($color, 'red', 'Red', 0);
        $black = $this->option($color, 'black', 'Black', 1);
        $blue = $this->option($color, 'blue', 'Blue', 2);
        $parent = Product::factory()->create(['type' => 'configurable', 'sku' => 'CONFIGURED', 'price' => 25]);
        $parent->translations()->create(['locale' => 'en', 'name' => 'Configured Product', 'url_key' => 'configured-product']);

        app(ProductService::class)->generateVariants($parent, [
            $color->id => [$red->id, $black->id],
        ]);

        return [$parent, $color, $red, $black, $blue];
    }

    private function option(Attribute $attribute, string $code, string $label, int $sortOrder): AttributeOption
    {
        $option = $attribute->options()->create([
            'code' => $code,
            'sort_order' => $sortOrder,
        ]);
        $option->translations()->create(['locale' => 'en', 'label' => $label]);

        return $option;
    }
}
