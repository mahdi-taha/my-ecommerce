<?php

namespace Database\Factories;

use App\Enums\OrderCancellationRequestStatus;
use App\Models\OrderCancellationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderCancellationRequestFactory extends Factory
{
    protected $model = OrderCancellationRequest::class;

    public function definition(): array
    {
        return [
            'reason' => fake()->sentence(),
            'status' => OrderCancellationRequestStatus::Pending,
            'pending_marker' => true,
            'admin_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
