<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationAudience;
use App\Models\NotificationChannel;
use App\Models\NotificationEvent;
use App\Models\NotificationRule;
use App\Services\NotificationConfigurationService;
use Database\Seeders\NotificationConfigurationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalized_configuration_is_seeded_disabled_and_idempotently(): void
    {
        $this->seed(NotificationConfigurationSeeder::class);

        $this->assertDatabaseCount('notification_events', 12);
        $this->assertDatabaseCount('notification_channels', 4);
        $this->assertDatabaseCount('notification_audiences', 2);
        $this->assertDatabaseCount('notification_rules', 96);
        $this->assertSame(0, NotificationRule::query()->where('is_enabled', true)->count());
        $this->assertFalse(Schema::hasTable('notification_deliveries'));

        $rule = NotificationRule::query()->firstOrFail();
        $rule->update(['is_enabled' => true]);
        $event = NotificationEvent::query()->firstOrFail();
        $event->update(['is_active' => false]);

        $this->seed(NotificationConfigurationSeeder::class);

        $this->assertDatabaseCount('notification_rules', 96);
        $this->assertTrue($rule->fresh()->is_enabled);
        $this->assertFalse($event->fresh()->is_active);
    }

    public function test_relationships_and_database_identity_constraints_are_authoritative(): void
    {
        $this->seed(NotificationConfigurationSeeder::class);
        $rule = NotificationRule::query()->with(['event', 'audience', 'channel'])->firstOrFail();

        $this->assertInstanceOf(NotificationEvent::class, $rule->event);
        $this->assertInstanceOf(NotificationAudience::class, $rule->audience);
        $this->assertInstanceOf(NotificationChannel::class, $rule->channel);

        $this->expectException(QueryException::class);
        NotificationRule::query()->create([
            'notification_event_id' => $rule->notification_event_id,
            'notification_audience_id' => $rule->notification_audience_id,
            'notification_channel_id' => $rule->notification_channel_id,
            'is_enabled' => false,
        ]);
    }

    public function test_service_updates_rules_transactionally_and_invalidates_cached_configuration(): void
    {
        $this->seed(NotificationConfigurationSeeder::class);
        $service = app(NotificationConfigurationService::class);
        $rules = NotificationRule::query()->limit(2)->get();

        Cache::put(NotificationConfigurationService::CACHE_KEY, [['stale' => true]]);
        $service->updateEnabledRules($rules->pluck('id')->all());

        $this->assertFalse(Cache::has(NotificationConfigurationService::CACHE_KEY));
        $this->assertSame(2, NotificationRule::query()->where('is_enabled', true)->count());
        $this->assertCount(2, $service->enabledRules());

        $service->updateEnabledRules([$rules->first()->id]);

        $this->assertSame(1, NotificationRule::query()->where('is_enabled', true)->count());
    }
}
