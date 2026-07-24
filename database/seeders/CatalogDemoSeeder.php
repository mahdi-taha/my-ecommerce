<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use RuntimeException;

class CatalogDemoSeeder extends Seeder
{
    private const DEMO_SKU = 'DEMO-SIMPLE-001';

    private const OPENING_QUANTITY = 25;

    public function run(InventoryService $inventoryService): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('CatalogDemoSeeder cannot run in production.');
        }

        $admin = User::query()
            ->where('email', 'test@example.com')
            ->first();

        if (! $admin) {
            throw new RuntimeException(
                'Catalog demo seeding requires the seeded admin user test@example.com.'
            );
        }

        $product = Product::updateOrCreate(
            ['sku' => self::DEMO_SKU],
            [
                'configurable_id' => null,
                'type' => 'simple',
                'product_number' => 'DEMO-0001',
                'price' => 29.99,
                'special_price' => null,
                'special_price_from' => null,
                'special_price_to' => null,
                'business_mode' => null,
                'is_new' => true,
                'is_featured' => true,
                'is_visible_individually' => true,
                'status' => true,
            ]
        );

        $product->translations()->updateOrCreate(
            ['locale' => 'en'],
            [
                'name' => 'Demo Wireless Headphones',
                'url_key' => 'demo-wireless-headphones',
                'short_description' => 'Wireless headphones used for Order lifecycle demonstrations.',
                'description' => 'A seeded standalone simple product with inventory for testing the complete Order lifecycle.',
                'meta_title' => 'Demo Wireless Headphones',
                'meta_description' => 'Demo product for Order lifecycle testing.',
                'meta_keywords' => 'demo, headphones, orders',
            ]
        );

        $product->translations()->updateOrCreate(
            ['locale' => 'ar'],
            [
                'name' => 'سماعات لاسلكية تجريبية',
                'url_key' => 'demo-wireless-headphones-ar',
                'short_description' => 'سماعات لاسلكية لاختبار دورة حياة الطلبات.',
                'description' => 'منتج بسيط تجريبي مزود بمخزون لاختبار دورة حياة الطلبات الكاملة.',
                'meta_title' => 'سماعات لاسلكية تجريبية',
                'meta_description' => 'منتج تجريبي لاختبار دورة حياة الطلبات.',
                'meta_keywords' => 'تجريبي، سماعات، طلبات',
            ]
        );

        $product->load('inventory');

        if (! $product->inventoryMovements()->exists()) {
            $inventoryService->setOpeningStock(
                $product,
                [
                    'quantity' => self::OPENING_QUANTITY,
                    'unit_cost' => 12.5,
                    'notes' => 'Opening stock for Order lifecycle demonstrations.',
                ],
                (int) $admin->getKey()
            );
        }

        $availableQuantity = (float) $product->inventory()->value('quantity');

        if ($availableQuantity < 7) {
            throw new RuntimeException(
                'The demo catalog product requires at least 7 available units before creating the Order lifecycle scenarios.'
            );
        }

        $this->command?->info(
            sprintf(
                '[catalog] %s | %s | available stock: %s',
                $product->sku,
                $product->translations()->where('locale', 'en')->value('name'),
                rtrim(rtrim(number_format($availableQuantity, 4, '.', ''), '0'), '.')
            )
        );
    }
}
