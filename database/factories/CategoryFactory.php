<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return ['parent_id' => null, 'position' => 0, 'level' => 0, 'logo_path' => null, 'banner_path' => null, 'status' => true];
    }

    public function childOf(Category|int $parent, int $level = 1): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent instanceof Category ? $parent->getKey() : $parent,
            'level' => $level,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => false]);
    }
}
