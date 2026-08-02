<?php

namespace Tests\Feature\Cms;

use Database\Seeders\CmsPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CmsPageMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_and_fixed_inactive_pages_are_seeded_idempotently(): void
    {
        $this->assertTrue(Schema::hasColumns('cms_pages', ['code', 'is_active', 'sort_order']));
        $this->seed(CmsPageSeeder::class);
        $this->seed(CmsPageSeeder::class);
        $this->assertDatabaseCount('cms_pages', 5);
        $this->assertDatabaseCount('cms_page_translations', 10);
        $this->assertDatabaseMissing('cms_pages', ['is_active' => true]);
    }
}
