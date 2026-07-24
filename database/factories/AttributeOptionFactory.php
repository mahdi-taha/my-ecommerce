<?php

namespace Database\Factories;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttributeOption> */
class AttributeOptionFactory extends Factory
{
    public function definition(): array
    {
        return ['attribute_id' => Attribute::factory()->state(['type' => AttributeType::Select->value]),
            'code' => fake()->unique()->slug(1), 'sort_order' => 0, 'swatch_value' => null];
    }
}
