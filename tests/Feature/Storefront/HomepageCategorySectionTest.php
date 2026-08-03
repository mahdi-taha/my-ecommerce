<?php

namespace Tests\Feature\Storefront;

use App\Http\Controllers\HomeController;
use App\Models\Category;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageCategorySectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_homepage_displays_only_localized_active_root_categories_in_order(): void
    {
        $later = $this->category('Later Root', position: 2);
        $first = $this->category('First Root', position: 1);
        $this->category('Child Category', position: 0, parent: $first);
        $this->category('Inactive Root', position: 0, active: false);
        $this->category('Arabic Root', position: 0, locale: 'ar');

        $response = $this->get(route('shop.home'))->assertOk();
        $section = $this->categorySection($response->getContent());

        $this->assertStringContainsString('First Root', $section);
        $this->assertStringContainsString(route('shop.categories.show', 'first-root'), $section);
        $this->assertStringContainsString('Later Root', $section);
        $this->assertLessThan(
            strpos($section, 'Later Root'),
            strpos($section, 'First Root')
        );
        $this->assertStringNotContainsString('Child Category', $section);
        $this->assertStringNotContainsString('Inactive Root', $section);
        $this->assertStringNotContainsString('Arabic Root', $section);
        $this->assertStringNotContainsString('products_count', $section);
        $this->assertStringContainsString('data-homepage-category-carousel', $section);
        $this->assertStringContainsString('data-category-count="2"', $section);
        $this->assertStringContainsString('storefront-category-carousel-slide', $section);
        $this->assertStringContainsString('storefront-category-carousel-media', $section);
        $this->assertStringContainsString('storefront-category-carousel-name', $section);
        $this->assertStringNotContainsString('owl-carousel', $section);

        $this->assertTrue($later->exists);
    }

    public function test_homepage_uses_existing_logo_and_falls_back_without_one(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('categories/logos/electronics.png', 'image');

        $this->category('With Logo', logoPath: 'categories/logos/electronics.png');
        $this->category('Missing Logo', position: 1, logoPath: 'categories/logos/missing.png');
        $this->category('Without Logo', position: 2);

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url('categories/logos/electronics.png'), false)
            ->assertSee('alt="With Logo"', false)
            ->assertSeeInOrder(['Missing Logo', 'Without Logo'])
            ->assertSee('fas fa-th-large fa-3x text-muted', false)
            ->assertSee('aria-hidden="true"', false)
            ->assertSee('href="'.route('shop.categories.show', 'with-logo').'"', false)
            ->assertDontSee('categories/logos/missing.png', false);
    }

    public function test_homepage_category_carousel_exposes_accessible_progressive_enhancement_hooks(): void
    {
        $this->category('Only Category');

        $response = $this->get(route('shop.home'))->assertOk();
        $section = $this->categorySection($response->getContent());

        $this->assertStringContainsString('data-category-count="1"', $section);
        $this->assertStringContainsString('data-previous-label="Previous categories"', $section);
        $this->assertStringContainsString('data-next-label="Next categories"', $section);
        $this->assertStringContainsString('aria-labelledby="homepage-categories-title"', $section);
        $this->assertMatchesRegularExpression(
            '/storefront-category-carousel-media.*storefront-category-carousel-name/s',
            $section
        );
        $this->assertStringNotContainsString('owl-carousel', $section);

        $script = file_get_contents(resource_path('js/shop/homepage-category-carousel.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('[0, 2]', $script);
        $this->assertStringContainsString('[576, 3]', $script);
        $this->assertStringContainsString('[768, 4]', $script);
        $this->assertStringContainsString('[992, 5]', $script);
        $this->assertStringContainsString('[1200, 6]', $script);
        $this->assertStringContainsString('loop: categoryCount > capacity', $script);
        $this->assertStringContainsString('nav: categoryCount > capacity', $script);
        $this->assertStringContainsString('autoplay: false', $script);
        $this->assertStringContainsString("classList.add('owl-carousel')", $script);
    }

    public function test_homepage_category_loading_uses_two_bounded_queries(): void
    {
        $root = $this->category('Root');
        $this->category('Child', parent: $root);
        $this->category('Another Root', position: 1);
        $categoryQueries = 0;

        DB::listen(function (QueryExecuted $query) use (&$categoryQueries): void {
            $sql = strtolower($query->sql);

            if (str_contains($sql, 'from "categories"') || str_contains($sql, 'from "category_translations"')) {
                $categoryQueries++;
            }
        });

        app(HomeController::class)->index();

        $this->assertSame(2, $categoryQueries);
    }

    private function category(
        string $name,
        int $position = 0,
        ?Category $parent = null,
        bool $active = true,
        string $locale = 'en',
        ?string $logoPath = null
    ): Category {
        $category = Category::factory()->create([
            'parent_id' => $parent?->id,
            'position' => $position,
            'level' => $parent ? $parent->level + 1 : 0,
            'logo_path' => $logoPath,
            'status' => $active,
        ]);
        $category->translations()->create([
            'locale' => $locale,
            'name' => $name,
            'slug' => str($name)->slug(),
        ]);

        return $category;
    }

    private function categorySection(string $content): string
    {
        $matched = preg_match(
            '/<div class="container-fluid py-5 bg-light" data-homepage-categories>(.*?)<section class="container-fluid py-5">/s',
            $content,
            $matches
        );

        $this->assertSame(1, $matched);

        return $matches[1];
    }
}
