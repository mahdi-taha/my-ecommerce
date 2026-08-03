<?php

namespace Tests\Feature\Catalog;

use App\Enums\AccountType;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Database\Seeders\LargeCatalogSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LargeCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_catalog_seeder_creates_a_connected_repeatable_bilingual_catalog(): void
    {
        $this->seed(SettingSeeder::class);
        $admin = User::factory()->create([
            'email' => 'catalog-admin@example.com',
            'account_type' => AccountType::Admin->value,
            'is_active' => true,
        ]);
        $manual = Product::factory()->create(['sku' => 'MANUAL-PRODUCT', 'product_number' => 'MANUAL-1']);
        $seeder = $this->reducedSeeder();

        app()->call([$seeder, 'run']);
        app()->call([$seeder, 'run']);

        $demoProducts = Product::query()->where('sku', 'like', 'DEMO-LARGE-%');
        $parents = Product::query()->where('sku', 'like', 'DEMO-LARGE-CFG-%')->where('type', ProductType::Configurable->value);
        $variants = Product::query()->where('sku', 'like', 'DEMO-LARGE-CFG-%-V%');

        $this->assertSame(24, $demoProducts->count());
        $this->assertSame(14, Product::query()->where('sku', 'like', 'DEMO-LARGE-SIMPLE-%')->count());
        $this->assertSame(2, $parents->count());
        $this->assertSame(8, $variants->count());
        $this->assertDatabaseHas('products', ['id' => $manual->id, 'sku' => 'MANUAL-PRODUCT']);

        $roots = Category::query()->whereNull('parent_id')->whereHas('translations', fn ($query) => $query
            ->where('locale', 'en')->where('slug', 'like', 'demo-large-root-%'));
        $this->assertSame(9, $roots->count());
        $this->assertGreaterThan(6, (clone $roots)->where('status', true)->count());
        $this->assertSame(4, Category::query()->where('level', 1)->whereHas('translations', fn ($query) => $query->where('slug', 'like', 'demo-large-child-%'))->count());
        $this->assertSame(2, Category::query()->where('level', 2)->whereHas('translations', fn ($query) => $query->where('slug', 'like', 'demo-large-leaf-%'))->count());
        $this->assertSame(32, $demoProducts->withCount('translations')->get()->sum('translations_count'));

        $parents->with(['superAttributes', 'variants.attributeValues'])->get()->each(function (Product $parent): void {
            $this->assertCount(2, $parent->superAttributes);
            $this->assertCount(4, $parent->variants);
            $this->assertCount(4, $parent->variants->map(fn (Product $variant) => $variant->attributeValues
                ->sortBy('attribute_id')->pluck('attribute_option_id')->implode('-'))->unique());
        });

        $inventoryProducts = Product::query()
            ->where('sku', 'like', 'DEMO-LARGE-%')
            ->where('type', ProductType::Simple->value)
            ->count();
        $this->assertSame($inventoryProducts, InventoryMovement::query()->whereHas('product', fn ($query) => $query->where('sku', 'like', 'DEMO-LARGE-%'))->distinct('product_id')->count('product_id'));
        $this->assertTrue(Product::query()->where('sku', 'like', 'DEMO-LARGE-%')->whereHas('inventory', fn ($query) => $query->outOfStock())->exists());
        $this->assertTrue(Product::query()->where('sku', 'like', 'DEMO-LARGE-%')->whereHas('inventory', fn ($query) => $query->lowStock())->exists());
        $this->assertTrue(Product::query()->where('sku', 'like', 'DEMO-LARGE-%')->whereHas('inventory', fn ($query) => $query->inStock())->exists());
        $this->assertTrue(Product::query()->where('sku', 'like', 'DEMO-LARGE-SIMPLE-%')->where('price', 0)->exists());
        $this->assertTrue(Product::query()->where('sku', 'like', 'DEMO-LARGE-SIMPLE-%')->where('is_featured', true)->exists());
        $this->assertTrue(Product::query()->where('sku', 'like', 'DEMO-LARGE-SIMPLE-%')->where('is_new', true)->exists());
        $this->assertTrue(Product::query()->where('sku', 'like', 'DEMO-LARGE-SIMPLE-%')->whereNotNull('special_price')->exists());

        $this->assertSame(4, Attribute::query()->where('code', 'like', 'demo_large_%')->count());
        $this->assertTrue(AttributeOption::query()->where('code', 'like', 'demo_large_%')->whereDoesntHave('productValues')->exists());
        $this->assertSame(0, ProductImage::query()->whereHas('product', fn ($query) => $query->where('sku', 'like', 'DEMO-LARGE-%'))->count());
        $this->assertFalse(Category::query()->whereHas('translations', fn ($query) => $query->where('slug', 'like', 'demo-large-%'))->whereNotNull('logo_path')->exists());
        $this->assertSame($admin->id, InventoryMovement::query()->whereHas('product', fn ($query) => $query->where('sku', 'like', 'DEMO-LARGE-%'))->value('created_by'));

        $this->get(route('shop.home'))->assertOk();
        $this->get(route('shop.products.index'))->assertOk();
        $this->get(route('shop.categories.show', ['slug' => 'demo-large-root-01']))->assertOk();
    }

    public function test_large_catalog_seeder_rejects_deterministic_identifier_collisions(): void
    {
        User::factory()->create(['account_type' => AccountType::Admin->value, 'is_active' => true]);
        Product::factory()->create([
            'sku' => 'DEMO-LARGE-SIMPLE-001',
            'product_number' => 'MANUAL-COLLISION',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Large catalog Product SKU collision');

        app()->call([$this->reducedSeeder(), 'run']);
    }

    public function test_large_catalog_seeder_requires_an_active_admin(): void
    {
        User::factory()->inactive()->create(['account_type' => AccountType::Admin->value]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires an active administrator');

        app()->call([$this->reducedSeeder(), 'run']);
    }

    public function test_large_catalog_seeder_refuses_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('only in local or testing environments');
            app()->call([$this->reducedSeeder(), 'run']);
        } finally {
            $this->app->detectEnvironment(fn (): string => 'testing');
        }
    }

    private function reducedSeeder(): LargeCatalogSeeder
    {
        return new class extends LargeCatalogSeeder
        {
            protected function configuration(): array
            {
                return [
                    'root_categories' => 9,
                    'child_categories' => 4,
                    'third_level_categories' => 2,
                    'simple_products' => 14,
                    'configurable_products' => 2,
                    'variants_per_configurable' => 4,
                ];
            }
        };
    }
}
