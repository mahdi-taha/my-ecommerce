<?php

namespace Tests\Feature\Storefront;

use App\Enums\AttributeType;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAttributeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_shop_and_category_sidebars_remove_category_selector_and_keep_search(): void
    {
        $category = $this->category('Phones', 'phones');

        $this->get(route('shop.products.index'))->assertOk()
            ->assertSee('name="q"', false)
            ->assertDontSee('name="category"', false);
        $this->get(route('shop.categories.show', 'phones'))->assertOk()
            ->assertSee('name="q"', false)
            ->assertDontSee('name="category"', false);
        $this->assertTrue($category->exists);
    }

    public function test_category_shows_only_assigned_supported_localized_attributes_and_used_options(): void
    {
        $category = $this->category('Phones', 'phones');
        [$color, $red, $blue] = $this->attribute('color', AttributeType::Select, ['red', 'blue']);
        [$storage, $large] = $this->attribute('storage', AttributeType::Multiselect, ['large']);
        [$unassigned, $unused] = $this->attribute('material', AttributeType::Select, ['metal']);
        [$text] = $this->attribute('model', AttributeType::Text, []);
        $category->filterableAttributes()->attach([$color->id, $storage->id, $text->id]);
        $product = $this->simple('Phone', $category);
        $this->value($product, $color, $red);
        $this->value($product, $storage, $large);
        $this->value($product, $unassigned, $unused);

        $this->get(route('shop.categories.show', 'phones'))->assertOk()
            ->assertSee('data-category-attribute="color"', false)
            ->assertSee('name="attributes[color][]" value="red"', false)
            ->assertDontSee('name="attributes[color][]" value="blue"', false)
            ->assertSee('data-category-attribute="storage"', false)
            ->assertDontSee('data-category-attribute="material"', false)
            ->assertDontSee('data-category-attribute="model"', false);

        $this->assertSame('blue', $blue->code);
    }

    public function test_ineligible_products_and_variants_do_not_contribute_facet_options(): void
    {
        $category = $this->category('Phones', 'phones');
        [$color, $red, $blue, $green] = $this->attribute('color', AttributeType::Select, ['red', 'blue', 'green']);
        $category->filterableAttributes()->attach($color);
        $eligible = $this->simple('Eligible', $category);
        $this->value($eligible, $color, $red);
        $inactive = $this->simple('Inactive', $category, ['status' => false]);
        $this->value($inactive, $color, $blue);
        $zero = $this->simple('Zero', $category, ['price' => 0]);
        $this->value($zero, $color, $green);

        $this->get(route('shop.categories.show', 'phones'))->assertOk()
            ->assertSee('value="red"', false)
            ->assertDontSee('value="blue"', false)
            ->assertDontSee('value="green"', false);
    }

    public function test_color_facets_render_safe_swatches_with_visible_labels_and_neutral_fallbacks(): void
    {
        $category = $this->category('Phones', 'phones');
        [$color, $red, $legacy, $missing] = $this->attribute(
            'color',
            AttributeType::Select,
            ['red', 'legacy', 'missing']
        );
        [$storage, $large] = $this->attribute('storage', AttributeType::Multiselect, ['large']);
        $color->update(['swatch_type' => 'color']);
        $storage->update(['swatch_type' => 'text']);
        $red->update(['swatch_value' => '#aa0000']);
        $legacy->update(['swatch_value' => '#000000;background:url(evil)']);
        $category->filterableAttributes()->attach([$color->id, $storage->id]);

        foreach ([[$color, $red], [$color, $legacy], [$color, $missing], [$storage, $large]] as [$attribute, $option]) {
            $product = $this->simple('Phone '.$attribute->code.' '.$option->code, $category);
            $this->value($product, $attribute, $option);
        }

        $response = $this->get(route('shop.categories.show', [
            'slug' => 'phones',
            'attributes' => ['color' => ['red']],
        ]))->assertOk()
            ->assertSee('name="attributes[color][]" value="red"', false)
            ->assertSee('style="--storefront-swatch-color: #AA0000"', false)
            ->assertSee('storefront-attribute-filter-swatch--missing', false)
            ->assertSee('Red')
            ->assertSee('Legacy')
            ->assertSee('Missing')
            ->assertSee('Large')
            ->assertDontSee('background:url(evil)', false);

        $facets = collect($response->viewData('attributeFacets'))->keyBy('code');
        $colorOptions = collect($facets->get('color')['options'])->keyBy('code');

        $this->assertSame('color', $facets->get('color')['swatch_type']);
        $this->assertSame('#AA0000', $colorOptions->get('red')['swatch_value']);
        $this->assertNull($colorOptions->get('legacy')['swatch_value']);
        $this->assertNull($colorOptions->get('missing')['swatch_value']);
        $this->assertSame('text', $facets->get('storage')['swatch_type']);
        $this->assertNull($facets->get('storage')['options'][0]['swatch_value']);
        $this->assertSame(3, substr_count($response->getContent(), 'storefront-attribute-filter-swatch '));
    }

    public function test_simple_filters_use_or_within_an_attribute_and_and_across_attributes(): void
    {
        $category = $this->category('Phones', 'phones');
        [$color, $red, $blue] = $this->attribute('color', AttributeType::Select, ['red', 'blue']);
        [$storage, $small, $large] = $this->attribute('storage', AttributeType::Multiselect, ['small', 'large']);
        $category->filterableAttributes()->attach([$color->id, $storage->id]);
        $redLarge = $this->simple('Red Large', $category);
        $this->value($redLarge, $color, $red);
        $this->value($redLarge, $storage, $large);
        $blueLarge = $this->simple('Blue Large', $category);
        $this->value($blueLarge, $color, $blue);
        $this->value($blueLarge, $storage, $large);
        $redSmall = $this->simple('Red Small', $category);
        $this->value($redSmall, $color, $red);
        $this->value($redSmall, $storage, $small);

        $products = $this->get(route('shop.categories.show', [
            'slug' => 'phones',
            'attributes' => ['color' => ['red', 'blue'], 'storage' => ['large']],
        ]))->assertOk()->viewData('products');

        $this->assertEqualsCanonicalizing([$redLarge->id, $blueLarge->id], $products->pluck('id')->all());
    }

    public function test_configurable_parent_requires_one_eligible_variant_to_match_the_complete_combination(): void
    {
        $category = $this->category('Phones', 'phones');
        [$color, $red, $blue] = $this->attribute('color', AttributeType::Select, ['red', 'blue']);
        [$size, $small, $large] = $this->attribute('size', AttributeType::Select, ['small', 'large']);
        $category->filterableAttributes()->attach([$color->id, $size->id]);
        $matching = $this->configurable('Matching', $category, [$color, $size], [[$red, $large]]);
        $split = $this->configurable('Split', $category, [$color, $size], [[$red, $small], [$blue, $large]]);

        $products = $this->get(route('shop.categories.show', [
            'slug' => 'phones',
            'attributes' => ['color' => ['red'], 'size' => ['large']],
        ]))->assertOk()->viewData('products');

        $this->assertSame([$matching->id], $products->pluck('id')->all());
        $this->assertFalse($products->contains($split));
    }

    public function test_invalid_attribute_shapes_codes_and_option_combinations_are_rejected(): void
    {
        $category = $this->category('Phones', 'phones');
        [$color, $red] = $this->attribute('color', AttributeType::Select, ['red']);
        [$size, $large] = $this->attribute('size', AttributeType::Select, ['large']);
        $category->filterableAttributes()->attach([$color->id, $size->id]);
        $product = $this->simple('Phone', $category);
        $this->value($product, $color, $red);
        $this->value($product, $size, $large);
        $url = route('shop.categories.show', 'phones');

        $this->from($url)->get($url.'?'.http_build_query(['attributes' => ['unknown' => ['red']]]))
            ->assertRedirect($url)->assertSessionHasErrors('attributes.unknown');
        $this->from($url)->get($url.'?'.http_build_query(['attributes' => ['color' => ['large']]]))
            ->assertRedirect($url)->assertSessionHasErrors('attributes.color');
        $this->from(route('shop.products.index'))->get(route('shop.products.index', [
            'attributes' => ['color' => ['red']],
        ]))->assertRedirect(route('shop.products.index'))->assertSessionHasErrors('attributes');
    }

    public function test_selected_attribute_filters_survive_sorting_and_pagination(): void
    {
        $category = $this->category('Phones', 'phones');
        [$color, $red] = $this->attribute('color', AttributeType::Select, ['red']);
        $category->filterableAttributes()->attach($color);
        foreach (range(1, 13) as $index) {
            $product = $this->simple('Red Phone '.$index, $category);
            $this->value($product, $color, $red);
        }

        $response = $this->get(route('shop.categories.show', [
            'slug' => 'phones',
            'attributes' => ['color' => ['red']],
            'sort' => 'price_asc',
        ]))->assertOk();

        $this->assertCount(12, $response->viewData('products')->items());
        $response->assertSee('name="attributes[color][]" value="red"', false)
            ->assertSee('attributes%5Bcolor%5D%5B0%5D=red', false)
            ->assertSee('sort=price_asc', false);
    }

    /** @return array<int, Attribute|AttributeOption> */
    private function attribute(string $code, AttributeType $type, array $optionCodes): array
    {
        $attribute = Attribute::factory()->create([
            'code' => $code,
            'type' => $type->value,
            'is_active' => true,
            'is_filterable' => true,
            'is_configurable' => $type === AttributeType::Select,
        ]);
        $attribute->translations()->create(['locale' => 'en', 'admin_name' => ucfirst($code)]);
        $options = collect($optionCodes)->map(function (string $optionCode, int $index) use ($attribute): AttributeOption {
            $option = $attribute->options()->create(['code' => $optionCode, 'sort_order' => $index]);
            $option->translations()->create(['locale' => 'en', 'label' => ucfirst($optionCode)]);

            return $option;
        });

        return [$attribute, ...$options];
    }

    private function category(string $name, string $slug): Category
    {
        $category = Category::factory()->create(['status' => true]);
        $category->translations()->create(['locale' => 'en', 'name' => $name, 'slug' => $slug]);

        return $category;
    }

    private function simple(string $name, Category $category, array $state = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'price' => 10,
            'status' => true,
            'is_visible_individually' => true,
        ], $state));
        $product->translations()->create(['locale' => 'en', 'name' => $name, 'url_key' => str($name)->slug().'-'.$product->id]);
        $product->inventory()->create(['quantity' => 5, 'average_cost' => 1]);
        $product->categories()->attach($category);

        return $product;
    }

    private function value(Product $product, Attribute $attribute, AttributeOption $option): void
    {
        $product->attributeValues()->create([
            'attribute_id' => $attribute->id,
            'attribute_option_id' => $option->id,
        ]);
    }

    private function configurable(
        string $name,
        Category $category,
        array $attributes,
        array $combinations
    ): Product {
        $parent = Product::factory()->create([
            'type' => ProductType::Configurable->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $parent->translations()->create(['locale' => 'en', 'name' => $name, 'url_key' => str($name)->slug().'-'.$parent->id]);
        $parent->categories()->attach($category);
        foreach ($attributes as $attribute) {
            $super = $parent->superAttributes()->create(['attribute_id' => $attribute->id]);
            $super->options()->sync($attribute->options()->pluck('id'));
        }
        foreach ($combinations as $index => $options) {
            $variant = Product::factory()->create([
                'type' => ProductType::Simple->value,
                'configurable_id' => $parent->id,
                'price' => 10 + $index,
                'status' => true,
                'is_visible_individually' => false,
            ]);
            foreach ($options as $option) {
                $this->value($variant, $option->attribute, $option);
            }
            $variant->inventory()->create(['quantity' => 5, 'average_cost' => 1]);
        }

        return $parent;
    }
}
