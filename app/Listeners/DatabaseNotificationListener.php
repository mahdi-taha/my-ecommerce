<?php

namespace App\Listeners;

use App\Enums\AccountType;
use App\Enums\NotificationAudienceCode;
use App\Enums\NotificationChannelCode;
use App\Events\NotificationDispatchResolved;
use App\Models\DatabaseNotification;
use App\Models\User;
use App\Services\NotificationMessageBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DatabaseNotificationListener
{
    public function __construct(private NotificationMessageBuilder $messages) {}

    public function handle(NotificationDispatchResolved $event): void
    {
        $decision = $event->decision;

        if (! $decision->enabled) {
            return;
        }

        $audiences = collect($decision->rules)
            ->where('channel', NotificationChannelCode::Database->value)
            ->pluck('audience')
            ->unique()
            ->values();

        if ($audiences->isEmpty()) {
            return;
        }

        try {
            $context = $this->messages->resolveContext($decision);

            if ($context === null) {
                return;
            }

            $defaultMessage = $this->messages->buildFromContext($decision, $context, 'en');

            $timestamp = now();
            $rows = [];

            if ($audiences->contains(NotificationAudienceCode::Customer->value)) {
                $customerId = $context['customer_id'];

                if ($customerId && User::query()
                    ->whereKey($customerId)
                    ->where('account_type', AccountType::Customer->value)
                    ->where('has_account', true)
                    ->where('is_active', true)
                    ->exists()) {
                    $message = $this->messages->buildFromContext(
                        $decision,
                        $context,
                        $context['customer_locale']
                    );
                    $rows[] = $this->row(
                        $decision,
                        NotificationAudienceCode::Customer->value,
                        $customerId,
                        $message,
                        $timestamp
                    );
                }
            }

            if ($audiences->contains(NotificationAudienceCode::Administrator->value)) {
                $adminIds = User::query()
                    ->admins()
                    ->active()
                    ->where('has_account', true)
                    ->orderBy('id')
                    ->pluck('id');

                foreach ($adminIds as $adminId) {
                    $rows[] = $this->row(
                        $decision,
                        NotificationAudienceCode::Administrator->value,
                        (int) $adminId,
                        $defaultMessage,
                        $timestamp
                    );
                }
            }

            if ($rows !== []) {
                DB::transaction(fn () => DatabaseNotification::query()->insert($rows));
            }
        } catch (Throwable $exception) {
            try {
                Log::error('Database notification delivery failed.', [
                    'event' => $decision->event,
                    'entity_type' => $decision->entityType,
                    'entity_id' => $decision->entityId,
                    'exception' => $exception,
                ]);
            } catch (Throwable) {
                // Notification diagnostics must never escape into the commerce flow.
            }
        }
    }

    private function row(
        $decision,
        string $audience,
        int $userId,
        array $message,
        $timestamp
    ): array {
        return [
            'audience_code' => $audience,
            'user_id' => $userId,
            'event_code' => $decision->event,
            'entity_type' => $decision->entityType,
            'entity_id' => $decision->entityId,
            'title' => $message['title'],
            'body' => $message['body'],
            'payload' => json_encode($message['payload'], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => $timestamp,
        ];
    }
}
