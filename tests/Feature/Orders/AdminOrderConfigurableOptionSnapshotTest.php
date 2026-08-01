<?php

namespace Tests\Feature\Orders;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminOrderConfigurableOptionSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_order_uses_eager_loaded_immutable_option_snapshots_with_legacy_fallback(): void
    {
        $attribute = Attribute::factory()->create([
            'code' => 'live-color',
            'type' => AttributeType::Select->value,
            'is_configurable' => true,
        ]);
        $attribute->translations()->create([
            'locale' => 'en',
            'admin_name' => 'Live Color',
        ]);
        $option = AttributeOption::factory()->create([
            'attribute_id' => $attribute->id,
            'code' => 'live-black',
        ]);
        $option->translations()->create([
            'locale' => 'en',
            'label' => 'Live Black',
        ]);

        $order = $this->createOrder();
        $configurable = $this->createItem($order, [
            'product_type' => 'variant',
            'sku' => 'CONFIGURABLE-SNAPSHOT',
            'name' => 'Configurable Snapshot Product',
            'option_summary' => 'Legacy summary must not duplicate normalized options',
        ]);
        $configurable->options()->createMany([
            [
                'attribute_code' => 'size',
                'attribute_name' => 'Historic Size',
                'option_code' => 'large',
                'option_label' => 'Historic Large',
            ],
            [
                'attribute_code' => 'color',
                'attribute_name' => 'Historic Color',
                'option_code' => 'black',
                'option_label' => 'Historic Black',
            ],
        ]);
        $this->createItem($order, [
            'sku' => 'LEGACY-SNAPSHOT',
            'name' => 'Legacy Snapshot Product',
            'option_summary' => 'Legacy Material: Cotton',
        ]);
        $this->createItem($order, [
            'sku' => 'NO-OPTIONS',
            'name' => 'No Option Product',
            'option_summary' => null,
        ]);
        $parent = $this->createItem($order, [
            'product_type' => 'bundle',
            'sku' => 'HISTORIC-PARENT',
            'name' => 'Historic Parent',
            'is_inventory_item' => false,
        ]);
        $child = $this->createItem($order, [
            'parent_order_item_id' => $parent->id,
            'product_type' => 'bundle_item',
            'sku' => 'HISTORIC-CHILD',
            'name' => 'Historic Child',
        ]);
        $child->options()->create([
            'attribute_code' => 'finish',
            'attribute_name' => 'Historic Finish',
            'option_code' => 'matte',
            'option_label' => 'Historic Matte',
        ]);

        $attribute->translations()->where('locale', 'en')->update([
            'admin_name' => 'Changed Live Color',
        ]);
        $option->translations()->where('locale', 'en')->update([
            'label' => 'Changed Live Black',
        ]);
        $option->delete();

        $optionQueries = [];
        DB::listen(function ($query) use (&$optionQueries): void {
            if (str_contains(strtolower($query->sql), 'order_item_options')) {
                $optionQueries[] = $query->sql;
            }
        });

        $response = $this->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.orders.show', $order));

        $response->assertOk()
            ->assertSeeInOrder([
                'Historic Color: Historic Black',
                'Historic Size: Historic Large',
            ])
            ->assertSee('Historic Finish: Historic Matte')
            ->assertSee('Legacy Material: Cotton')
            ->assertSee('No Option Product')
            ->assertDontSee('Legacy summary must not duplicate normalized options')
            ->assertDontSee('Changed Live Color')
            ->assertDontSee('Changed Live Black');

        $this->assertCount(2, $optionQueries);
    }

    /** @param array<string, mixed> $overrides */
    private function createOrder(array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'order_number' => 'ORD-ADMIN-OPTIONS-'.uniqid(),
            'customer_email' => 'snapshot@example.test',
            'customer_first_name' => 'Snapshot',
            'customer_last_name' => 'Customer',
            'customer_phone' => '70123456',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'requires_payment_before_processing' => false,
            'subtotal' => '50.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '50.0000',
            'placed_at' => now(),
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function createItem(Order $order, array $overrides = []): OrderItem
    {
        return $order->items()->create(array_merge([
            'product_id' => null,
            'product_type' => 'simple',
            'sku' => 'SNAPSHOT-'.uniqid(),
            'name' => 'Snapshot Product',
            'quantity' => '1.0000',
            'original_unit_price' => '10.0000',
            'unit_price' => '10.0000',
            'tax_name' => null,
            'tax_rate' => '0.0000',
            'tax_amount' => '0.0000',
            'row_subtotal' => '10.0000',
            'discount_amount' => '0.0000',
            'row_total' => '10.0000',
            'unit_cost' => null,
            'is_inventory_item' => true,
        ], $overrides));
    }
}
