<?php

namespace Tests\Unit\Notifications;

use App\Enums\NotificationEventCode;
use App\Models\NotificationAudience;
use App\Models\NotificationChannel;
use App\Models\NotificationEvent;
use App\Models\NotificationRule;
use App\Services\NotificationConfigurationService;
use App\Services\NotificationEventService;
use Database\Seeders\NotificationConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationEventServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_and_enabled_rules_produce_immutable_deterministic_decisions(): void
    {
        $this->seed(NotificationConfigurationSeeder::class);
        $service = app(NotificationEventService::class);

        $disabled = $service->resolve(NotificationEventCode::OrderPlaced, 'order', 10);

        $this->assertFalse($disabled->enabled);
        $this->assertSame('no_enabled_rules', $disabled->skippedReason);

        $rules = NotificationRule::query()
            ->whereHas('event', fn ($query) => $query->where('code', 'order_placed'))
            ->whereHas('channel', fn ($query) => $query->where('code', 'email'))
            ->get();
        app(NotificationConfigurationService::class)->updateEnabledRules($rules->pluck('id')->all());

        $enabled = $service->resolve(NotificationEventCode::OrderPlaced, 'order', 10);

        $this->assertTrue($enabled->enabled);
        $this->assertSame(['administrator', 'customer'], $enabled->audiences);
        $this->assertSame(['email'], $enabled->channels);
        $this->assertCount(2, $enabled->rules);
        $this->assertNull($enabled->skippedReason);
    }

    public function test_inactive_event_audience_and_channel_are_excluded(): void
    {
        $this->seed(NotificationConfigurationSeeder::class);
        $configuration = app(NotificationConfigurationService::class);
        $service = app(NotificationEventService::class);
        $rule = NotificationRule::query()
            ->whereHas('event', fn ($query) => $query->where('code', 'order_cancelled'))
            ->whereHas('audience', fn ($query) => $query->where('code', 'customer'))
            ->whereHas('channel', fn ($query) => $query->where('code', 'email'))
            ->firstOrFail();
        $configuration->updateEnabledRules([$rule->id]);

        NotificationChannel::query()->where('code', 'email')->update(['is_active' => false]);
        cache()->forget(NotificationConfigurationService::CACHE_KEY);
        $this->assertSame(
            'no_enabled_rules',
            $service->resolve(NotificationEventCode::OrderCancelled, 'order', 1)->skippedReason
        );

        NotificationChannel::query()->where('code', 'email')->update(['is_active' => true]);
        NotificationAudience::query()->where('code', 'customer')->update(['is_active' => false]);
        cache()->forget(NotificationConfigurationService::CACHE_KEY);
        $this->assertSame(
            'no_enabled_rules',
            $service->resolve(NotificationEventCode::OrderCancelled, 'order', 1)->skippedReason
        );

        NotificationEvent::query()->where('code', 'order_cancelled')->update(['is_active' => false]);
        $this->assertSame(
            'event_inactive',
            $service->resolve(NotificationEventCode::OrderCancelled, 'order', 1)->skippedReason
        );
    }
}
