<?php

namespace App\Events;

use App\DTOs\Notifications\NotificationDispatchDecision;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class NotificationDispatchResolved
{
    use Dispatchable;

    public function __construct(public NotificationDispatchDecision $decision) {}
}
