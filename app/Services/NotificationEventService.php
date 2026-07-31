<?php

namespace App\Services;

use App\DTOs\Notifications\NotificationDispatchDecision;
use App\Enums\NotificationEventCode;
use App\Models\NotificationEvent;

class NotificationEventService
{
    public function __construct(private NotificationConfigurationService $configuration) {}

    public function resolve(
        NotificationEventCode $eventCode,
        string $entityType,
        int $entityId
    ): NotificationDispatchDecision {
        $event = NotificationEvent::query()
            ->where('code', $eventCode->value)
            ->first(['id', 'code', 'is_active']);

        if (! $event) {
            return $this->skipped($eventCode, $entityType, $entityId, 'event_not_found');
        }

        if (! $event->is_active) {
            return $this->skipped($eventCode, $entityType, $entityId, 'event_inactive');
        }

        $rules = $this->configuration->enabledRulesFor($eventCode->value);

        if ($rules === []) {
            return $this->skipped($eventCode, $entityType, $entityId, 'no_enabled_rules');
        }

        return new NotificationDispatchDecision(
            event: $eventCode->value,
            entityType: $entityType,
            entityId: $entityId,
            audiences: collect($rules)->pluck('audience')->unique()->sort()->values()->all(),
            channels: collect($rules)->pluck('channel')->unique()->sort()->values()->all(),
            rules: $rules,
            enabled: true,
        );
    }

    private function skipped(
        NotificationEventCode $eventCode,
        string $entityType,
        int $entityId,
        string $reason
    ): NotificationDispatchDecision {
        return new NotificationDispatchDecision(
            event: $eventCode->value,
            entityType: $entityType,
            entityId: $entityId,
            audiences: [],
            channels: [],
            rules: [],
            enabled: false,
            skippedReason: $reason,
        );
    }
}
