<?php

namespace App\Listeners;

use App\Events\CommerceEventOccurred;
use App\Events\NotificationDispatchResolved;
use App\Services\NotificationEventService;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResolveNotificationEvent
{
    public function __construct(private NotificationEventService $notifications) {}

    public function handle(CommerceEventOccurred $event): void
    {
        try {
            NotificationDispatchResolved::dispatch($this->notifications->resolve(
                $event->event,
                $event->entityType,
                $event->entityId
            ));
        } catch (Throwable $exception) {
            try {
                Log::error('Notification event resolution failed.', [
                    'event' => $event->event->value,
                    'entity_type' => $event->entityType,
                    'entity_id' => $event->entityId,
                    'exception' => $exception,
                ]);
            } catch (Throwable) {
                // Notification diagnostics must never escape into the commerce flow.
            }
        }
    }
}
