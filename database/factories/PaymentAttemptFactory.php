<?php

namespace Database\Factories;

use App\Enums\PaymentAttemptStatus;
use App\Models\OrderPayment;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentAttempt> */
class PaymentAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_payment_id' => OrderPayment::factory(),
            'attempt_number' => 1,
            'provider' => null,
            'status' => PaymentAttemptStatus::Pending,
            'amount' => '10.0000',
            'currency_code' => 'USD',
            'transaction_reference' => null,
            'customer_note' => null,
            'provider_transaction_id' => null,
            'failure_code' => null,
            'failure_message' => null,
            'metadata_json' => null,
            'initiated_at' => now(),
            'completed_at' => null,
        ];
    }
}
