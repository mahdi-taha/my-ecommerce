<?php

namespace Database\Factories;

use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderPayment> */
class OrderPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_number' => 'PAY-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'order_id' => Order::factory(),
            'payment_method_id' => null,
            'method_code' => 'cash_on_delivery',
            'method_name' => 'Cash on Delivery',
            'method_type' => PaymentMethodType::Offline->value,
            'amount' => '10.0000',
            'currency_code' => 'USD',
            'status' => PaymentStatus::Pending,
            'paid_amount' => '0.0000',
            'paid_at' => null,
        ];
    }
}
