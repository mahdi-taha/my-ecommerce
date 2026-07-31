<?php

namespace App\Events;

use App\Enums\NotificationEventCode;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CommerceEventOccurred implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public NotificationEventCode $event,
        public string $entityType,
        public int $entityId,
    ) {}
}
