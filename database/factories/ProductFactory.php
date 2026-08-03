<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return ['configurable_id' => null, 'type' => ProductType::Simple->value, 'product_number' => null,
            'sku' => fake()->unique()->bothify('SKU-#####'), 'price' => 10, 'special_price' => null,
            'special_price_from' => null, 'special_price_to' => null,
            'is_new' => false, 'is_featured' => false, 'is_visible_individually' => true, 'status' => true];
    }

    public function configurable(): static
    {
        return $this->state(fn (): array => [
            'configurable_id' => null,
            'type' => ProductType::Configurable->value,
            'is_visible_individually' => true,
        ]);
    }

    public function variant(Product|int $parent): static
    {
        return $this->state(fn (): array => [
            'configurable_id' => $parent instanceof Product ? $parent->getKey() : $parent,
            'type' => ProductType::Simple->value,
            'is_visible_individually' => false,
        ]);
    }

    public function onSale(?CarbonInterface $from = null, ?CarbonInterface $to = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'special_price' => max(0.01, (float) $attributes['price'] * .8),
            'special_price_from' => $from ?? now()->subDay(),
            'special_price_to' => $to ?? now()->addMonth(),
        ]);
    }

    public function futureSale(): static
    {
        return $this->onSale(now()->addWeek(), now()->addMonth());
    }

    public function expiredSale(): static
    {
        return $this->onSale(now()->subMonth(), now()->subWeek());
    }

    public function zeroPrice(): static
    {
        return $this->state(fn (): array => ['price' => 0, 'special_price' => null]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }

    public function newProduct(): static
    {
        return $this->state(fn (): array => ['is_new' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => false]);
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['is_visible_individually' => false]);
    }
}
