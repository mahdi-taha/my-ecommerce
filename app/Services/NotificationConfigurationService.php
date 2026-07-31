<?php

namespace App\Services;

use App\Models\NotificationEvent;
use App\Models\NotificationRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NotificationConfigurationService
{
    public const CACHE_KEY = 'notifications.rules';

    public function administrationMatrix(): Collection
    {
        return NotificationEvent::query()
            ->with([
                'rules' => fn ($query) => $query
                    ->with(['audience:id,code,name', 'channel:id,code,name'])
                    ->orderBy('notification_audience_id')
                    ->orderBy('notification_channel_id'),
            ])
            ->orderBy('category')
            ->orderBy('id')
            ->get();
    }

    public function enabledRules(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => NotificationRule::query()
            ->where('is_enabled', true)
            ->whereHas('event', fn ($query) => $query->where('is_active', true))
            ->whereHas('audience', fn ($query) => $query->where('is_active', true))
            ->whereHas('channel', fn ($query) => $query->where('is_active', true))
            ->with([
                'event:id,code',
                'audience:id,code',
                'channel:id,code',
            ])
            ->orderBy('notification_event_id')
            ->orderBy('notification_audience_id')
            ->orderBy('notification_channel_id')
            ->get()
            ->map(fn (NotificationRule $rule) => [
                'event' => $rule->event->code,
                'audience' => $rule->audience->code,
                'channel' => $rule->channel->code,
            ])
            ->all());
    }

    public function enabledRulesFor(string $eventCode): array
    {
        return collect($this->enabledRules())
            ->where('event', $eventCode)
            ->map(fn (array $rule) => [
                'audience' => $rule['audience'],
                'channel' => $rule['channel'],
            ])
            ->sortBy(fn (array $rule) => $rule['audience'].'|'.$rule['channel'])
            ->values()
            ->all();
    }

    public function updateEnabledRules(array $enabledRuleIds): void
    {
        $ids = collect($enabledRuleIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        DB::transaction(function () use ($ids): void {
            $rules = NotificationRule::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'is_enabled']);
            $enabled = $ids->flip();

            foreach ($rules as $rule) {
                $shouldEnable = $enabled->has($rule->getKey());

                if ($rule->is_enabled !== $shouldEnable) {
                    $rule->update(['is_enabled' => $shouldEnable]);
                }
            }
        });

        Cache::forget(self::CACHE_KEY);
    }
}
