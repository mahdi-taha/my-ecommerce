<?php

namespace App\DTOs\Notifications;

final readonly class NotificationDispatchDecision
{
    public function __construct(
        public string $event,
        public string $entityType,
        public int $entityId,
        public array $audiences,
        public array $channels,
        public array $rules,
        public bool $enabled,
        public ?string $skippedReason = null,
    ) {}
}
