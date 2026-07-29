<?php

namespace Database\Factories;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomerAddress> */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'label' => fake()->randomElement(['Home', 'Office']),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => null,
            'country_code' => 'LB',
            'is_default_shipping' => false,
            'is_default_billing' => false,
        ];
    }
}
