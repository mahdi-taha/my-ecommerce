<?php

namespace Tests\Feature\Notifications;

use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseNotificationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_lists_and_reads_only_owned_customer_notifications(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $owned = $this->notification($customer, 'customer', 'Owned notification');
        $foreign = $this->notification($other, 'customer', 'Foreign notification');

        $response = $this->actingAs($customer, 'customer')->get(route('shop.account.notifications.index'));

        $response->assertOk()
            ->assertSee('Owned notification')
            ->assertDontSee('Foreign notification')
            ->assertSee('1');

        $this->actingAs($customer, 'customer')
            ->patch(route('shop.account.notifications.read', $owned))
            ->assertRedirect();
        $this->assertNotNull($owned->fresh()->read_at);

        $this->actingAs($customer, 'customer')
            ->patch(route('shop.account.notifications.read', $foreign))
            ->assertNotFound();
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_guest_is_redirected_from_customer_notifications(): void
    {
        $this->get(route('shop.account.notifications.index'))
            ->assertRedirect();
    }

    public function test_admin_lists_and_reads_only_owned_administrator_notifications(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();
        $owned = $this->notification($admin, 'administrator', 'Admin notification');
        $foreign = $this->notification($other, 'administrator', 'Other admin notification');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Admin notification')
            ->assertDontSee('Other admin notification');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.notifications.read', $owned))
            ->assertRedirect();
        $this->assertNotNull($owned->fresh()->read_at);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.notifications.read', $foreign))
            ->assertNotFound();
    }

    public function test_customer_navigation_counts_owned_unread_customer_notifications_once(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();

        $this->notification($customer, 'customer', 'Unread customer notification');
        $read = $this->notification($customer, 'customer', 'Read customer notification');
        $read->update(['read_at' => now()]);
        $this->notification($customer, 'administrator', 'Wrong audience notification');
        $this->notification($other, 'customer', 'Foreign customer notification');

        $countQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$countQueries): void {
            $sql = strtolower($query->sql);

            if (str_contains($sql, 'database_notifications') && str_contains($sql, 'count(')) {
                $countQueries++;
            }
        });

        $this->actingAs($customer, 'customer');
        $html = view('customer.account._navigation')->render();

        $this->assertMatchesRegularExpression('/badge[^>]*>\s*1\s*<\/span>/', $html);
        $this->assertSame(1, $countQueries);
    }

    public function test_admin_topbar_counts_owned_unread_administrator_notifications_once(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();

        $this->notification($admin, 'administrator', 'Unread administrator notification');
        $read = $this->notification($admin, 'administrator', 'Read administrator notification');
        $read->update(['read_at' => now()]);
        $this->notification($admin, 'customer', 'Wrong audience notification');
        $this->notification($other, 'administrator', 'Foreign administrator notification');

        $countQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$countQueries): void {
            $sql = strtolower($query->sql);

            if (str_contains($sql, 'database_notifications') && str_contains($sql, 'count(')) {
                $countQueries++;
            }
        });

        $this->actingAs($admin, 'admin');
        $html = view('components.admin-topbar')->render();

        $this->assertMatchesRegularExpression('/badge[^>]*>\s*1\s*<\/span>/', $html);
        $this->assertSame(1, $countQueries);
    }

    private function notification(User $user, string $audience, string $title): DatabaseNotification
    {
        return DatabaseNotification::query()->create([
            'audience_code' => $audience,
            'user_id' => $user->id,
            'event_code' => 'order_placed',
            'entity_type' => 'order',
            'entity_id' => 1,
            'title' => $title,
            'body' => 'Notification body.',
            'payload' => ['order_id' => 1],
            'created_at' => now(),
        ]);
    }
}
