<?php

namespace Tests\Feature\Catalog;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\User;
use App\Services\AttributeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttributeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_attribute_and_translated_options_are_created_transactionally(): void
    {
        $service = app(AttributeService::class);
        $attribute = $service->create(['attribute_code' => 'color', 'attribute_type' => AttributeType::Select->value,
            'attribute_name_en' => 'Color', 'attribute_name_ar' => 'اللون', 'attribute_sort_order' => 0,
            'attribute_swatch_type' => 'dropdown', 'is_required' => true, 'is_configurable' => true,
            'is_filterable' => true, 'is_visible_on_front' => true]);
        $service->saveOptions($attribute, ['swatch_type' => 'dropdown', 'options' => [[
            'code' => 'red', 'label_en' => 'Red', 'label_ar' => 'أحمر', 'sort_order' => 0,
        ]]]);

        $this->assertDatabaseHas('attribute_options', ['attribute_id' => $attribute->id, 'code' => 'red']);
        $this->assertCount(2, $attribute->options()->firstOrFail()->translations);
    }

    public function test_used_option_code_is_immutable_and_option_cannot_be_deleted(): void
    {
        [$attribute, $option] = $this->usedOption();

        try {
            app(AttributeService::class)->saveOptions($attribute, ['swatch_type' => 'dropdown', 'options' => [[
                'id' => $option->id, 'code' => 'changed', 'label_en' => 'Red', 'label_ar' => 'أحمر', 'sort_order' => 0,
            ]]]);
            $this->fail('A referenced option code was changed.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Option code red is immutable because it is in use.',
                $exception->errors()['options.0.code'][0]
            );
        }

        $this->assertSame('red', $option->fresh()->code);
    }

    public function test_new_option_code_is_generated_from_the_english_label(): void
    {
        $attribute = Attribute::factory()->create(['type' => 'select', 'swatch_type' => 'dropdown']);

        app(AttributeService::class)->saveOptions($attribute, ['swatch_type' => 'dropdown', 'options' => [[
            'label_en' => 'Bright Red', 'label_ar' => 'أحمر فاتح', 'sort_order' => 0,
        ]]]);

        $this->assertSame('bright_red', $attribute->options()->sole()->code);
    }

    public function test_generated_option_code_collisions_are_resolved_deterministically(): void
    {
        $attribute = Attribute::factory()->create(['type' => 'select', 'swatch_type' => 'dropdown']);

        app(AttributeService::class)->saveOptions($attribute, ['swatch_type' => 'dropdown', 'options' => [
            ['label_en' => 'Red', 'label_ar' => 'أحمر', 'sort_order' => 0],
            ['label_en' => 'Red', 'label_ar' => 'أحمر آخر', 'sort_order' => 1],
        ]]);

        $this->assertSame(['red', 'red_2'], $attribute->options()->pluck('code')->all());
    }

    public function test_generated_option_code_has_a_deterministic_empty_normalization_fallback(): void
    {
        $attribute = Attribute::factory()->create(['type' => 'select', 'swatch_type' => 'dropdown']);

        app(AttributeService::class)->saveOptions($attribute, ['swatch_type' => 'dropdown', 'options' => [[
            'label_en' => '!!!', 'label_ar' => 'خيار', 'sort_order' => 0,
        ]]]);

        $expected = 'option_'.substr(hash('sha256', '!!!'), 0, 12);
        $this->assertSame($expected, $attribute->options()->sole()->code);
    }

    public function test_unused_option_code_can_be_explicitly_edited_and_is_normalized(): void
    {
        $attribute = Attribute::factory()->create(['type' => 'select', 'swatch_type' => 'dropdown']);
        $option = $attribute->options()->create(['code' => 'red', 'sort_order' => 0]);

        app(AttributeService::class)->saveOptions($attribute, ['swatch_type' => 'dropdown', 'options' => [[
            'id' => $option->id, 'code' => ' Bright Red ', 'label_en' => 'Red', 'label_ar' => 'أحمر', 'sort_order' => 0,
        ]]]);

        $this->assertSame('bright_red', $option->fresh()->code);
    }

    public function test_existing_option_code_is_preserved_when_only_labels_change(): void
    {
        $attribute = Attribute::factory()->create(['type' => 'select', 'swatch_type' => 'dropdown']);
        $option = $attribute->options()->create(['code' => 'stable_code', 'sort_order' => 0]);

        app(AttributeService::class)->saveOptions($attribute, ['swatch_type' => 'dropdown', 'options' => [[
            'id' => $option->id, 'label_en' => 'Changed Label', 'label_ar' => 'تسمية معدلة', 'sort_order' => 0,
        ]]]);

        $this->assertSame('stable_code', $option->fresh()->code);
    }

    public function test_submitted_code_is_ignored_for_a_new_option(): void
    {
        $attribute = Attribute::factory()->create(['type' => 'select', 'swatch_type' => 'dropdown']);

        app(AttributeService::class)->saveOptions($attribute, ['swatch_type' => 'dropdown', 'options' => [[
            'code' => 'manually_selected', 'label_en' => 'Blue', 'label_ar' => 'أزرق', 'sort_order' => 0,
        ]]]);

        $this->assertSame('blue', $attribute->options()->sole()->code);
    }

    public function test_color_swatches_require_safe_hex_values_and_are_normalized(): void
    {
        $attribute = Attribute::factory()->create(['type' => 'select', 'swatch_type' => 'color']);
        $admin = User::factory()->create();
        $payload = [
            'swatch_type' => 'color',
            'options' => [[
                'label_en' => 'Blue',
                'label_ar' => 'أزرق',
                'sort_order' => 0,
                'swatch_value' => '#aabbcc',
            ]],
        ];

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.attribute-options.save', $attribute), $payload)
            ->assertOk();
        $this->assertSame('#AABBCC', $attribute->options()->sole()->swatch_value);

        foreach ([null, '#ABC', 'red', '#000000;background:url(javascript:alert(1))'] as $invalid) {
            $payload['options'][0]['swatch_value'] = $invalid;
            $this->actingAs($admin, 'admin')
                ->postJson(route('admin.attribute-options.save', $attribute), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('options.0.swatch_value');
        }
    }

    public function test_non_color_presentation_clears_a_stored_color(): void
    {
        $attribute = Attribute::factory()->create(['type' => 'select', 'swatch_type' => 'color']);
        $option = $attribute->options()->create([
            'code' => 'blue',
            'sort_order' => 0,
            'swatch_value' => '#0000FF',
        ]);

        app(AttributeService::class)->saveOptions($attribute, [
            'swatch_type' => 'text',
            'options' => [[
                'id' => $option->id,
                'label_en' => 'Blue',
                'label_ar' => 'أزرق',
                'sort_order' => 0,
            ]],
        ]);

        $this->assertNull($option->fresh()->swatch_value);
    }

    public function test_unused_attribute_can_be_deleted(): void
    {
        $attribute = Attribute::factory()->create();
        $attribute->translations()->create(['locale' => 'en', 'admin_name' => 'Material']);
        $option = $attribute->options()->create(['code' => 'cotton', 'sort_order' => 0]);
        $option->translations()->create(['locale' => 'en', 'label' => 'Cotton']);

        $response = $this
            ->actingAs(User::factory()->create(), 'admin')
            ->deleteJson(route('admin.attributes.destroy', $attribute));

        $response
            ->assertOk()
            ->assertJson(['message' => 'Attribute deleted successfully.']);
        $this->assertDatabaseMissing('attributes', ['id' => $attribute->id]);
        $this->assertDatabaseMissing('attribute_options', ['id' => $option->id]);
        $this->assertDatabaseMissing('attribute_translations', ['attribute_id' => $attribute->id]);
        $this->assertDatabaseMissing('attribute_translation_options', ['attribute_option_id' => $option->id]);
    }

    public function test_referenced_attribute_cannot_be_deleted(): void
    {
        [$attribute] = $this->usedOption();

        $response = $this
            ->actingAs(User::factory()->create(), 'admin')
            ->deleteJson(route('admin.attributes.destroy', $attribute));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'attribute' => 'This attribute is in use and cannot be deleted.',
            ]);
        $this->assertDatabaseHas('attributes', ['id' => $attribute->id]);
    }

    private function usedOption(): array
    {
        $attribute = Attribute::factory()->create(['type' => 'select', 'swatch_type' => 'dropdown']);
        $option = $attribute->options()->create(['code' => 'red', 'sort_order' => 0]);
        $product = Product::factory()->create();
        $product->attributeValues()->create(['attribute_id' => $attribute->id, 'attribute_option_id' => $option->id]);

        return [$attribute, $option];
    }
}
