<?php

namespace Tests\Feature\Cms;

use App\Services\StorefrontContentService;
use Database\Seeders\CmsPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CmsContentQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_localized_page_and_footer_resolution_are_cached(): void
    {
        $this->seed(CmsPageSeeder::class);
        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $service = app(StorefrontContentService::class);
        $service->footerPages('en');
        $first = count(DB::getQueryLog());
        $service->footerPages('en');
        $this->assertSame($first, count(DB::getQueryLog()));
        $this->assertLessThanOrEqual(2, $first);
    }
}
