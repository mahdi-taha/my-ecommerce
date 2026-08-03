<?php

namespace Database\Factories;

use App\Enums\AttributeType;
use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attribute> */
class AttributeFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->slug(2), 'type' => AttributeType::Text->value, 'swatch_type' => null,
            'is_required' => false, 'is_configurable' => false, 'is_filterable' => false,
            'is_visible_on_front' => true, 'is_active' => true, 'sort_order' => 0];
    }

    public function select(): static
    {
        return $this->state(fn (): array => [
            'type' => AttributeType::Select->value,
            'swatch_type' => 'dropdown',
        ]);
    }

    public function multiselect(): static
    {
        return $this->state(fn (): array => [
            'type' => AttributeType::Multiselect->value,
            'swatch_type' => 'dropdown',
            'is_configurable' => false,
        ]);
    }

    public function filterable(): static
    {
        return $this->state(fn (): array => ['is_filterable' => true]);
    }

    public function configurable(): static
    {
        return $this->select()->state(fn (): array => [
            'is_configurable' => true,
            'is_required' => true,
        ]);
    }
}
