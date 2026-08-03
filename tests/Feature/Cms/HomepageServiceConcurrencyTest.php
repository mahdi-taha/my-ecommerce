<?php

namespace Tests\Feature\Cms;

use App\Enums\HomepageServiceIcon;
use App\Models\HomepageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\ConcurrentProcessRunner;
use Tests\TestCase;

class HomepageServiceConcurrencyTest extends TestCase
{
    /** @var list<int> */
    private array $serviceIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->environment('testing')) {
            throw new RuntimeException('The concurrency suite may run only with APP_ENV=testing.');
        }
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('True homepage-service locking is verified only against MySQL.');
        }
        $database = (string) DB::connection()->getDatabaseName();
        if (! preg_match('/test|testing/i', $database)) {
            throw new RuntimeException("Refusing to run concurrency tests against database [{$database}].");
        }
        foreach (['homepage_services', 'homepage_service_translations', 'homepage_service_locks'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("The migrated MySQL test table [{$table}] is required.");
            }
        }
    }

    protected function tearDown(): void
    {
        if ($this->serviceIds !== [] && DB::getDriverName() === 'mysql') {
            HomepageService::query()->whereKey($this->serviceIds)->delete();
        }

        parent::tearDown();
    }

    public function test_two_concurrent_activations_cannot_exceed_six_active_services(): void
    {
        foreach (range(1, 5) as $position) {
            $this->createService(true, $position);
        }
        $first = $this->createService(false, 6);
        $second = $this->createService(false, 7);

        $results = (new ConcurrentProcessRunner(45))->run([
            ['action' => 'activate_homepage_service', 'payload' => [
                'service_id' => $first->id,
                'data' => $this->payload(6),
            ]],
            ['action' => 'activate_homepage_service', 'payload' => [
                'service_id' => $second->id,
                'data' => $this->payload(7),
            ]],
        ]);

        $this->assertSame(1, collect($results)->where('successful', true)->count());
        $this->assertSame(1, collect($results)->where('successful', false)->count());
        $this->assertSame(6, HomepageService::query()->active()->whereKey($this->serviceIds)->count());
    }

    private function createService(bool $active, int $position): HomepageService
    {
        $service = HomepageService::query()->create([
            'icon' => HomepageServiceIcon::Support,
            'is_active' => $active,
            'sort_order' => $position,
        ]);
        $service->translations()->createMany([
            ['locale' => 'en', 'title' => "Concurrency {$position}", 'description' => 'Description'],
            ['locale' => 'ar', 'title' => "Concurrency ar {$position}", 'description' => 'Description'],
        ]);
        $this->serviceIds[] = $service->id;

        return $service;
    }

    private function payload(int $position): array
    {
        return [
            'icon' => HomepageServiceIcon::Support->value,
            'is_active' => true,
            'sort_order' => $position,
            'title_en' => "Concurrency {$position}",
            'description_en' => 'Description',
            'title_ar' => "Concurrency ar {$position}",
            'description_ar' => 'Description',
        ];
    }
}
