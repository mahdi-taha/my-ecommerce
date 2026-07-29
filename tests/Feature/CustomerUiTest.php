<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_customer_pages_load_for_admin_and_remain_protected(): void
    {
        $customer = User::factory()->customer()->create();

        $this->get(route('admin.customers.index'))->assertRedirect(route('admin.login'));

        $this->actingAs($this->admin)->get(route('admin.customers.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.customers.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.customers.edit', $customer))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.customers.password.edit', $customer))->assertOk();
    }

    public function test_datatable_excludes_administrators_and_searches_customer_fields(): void
    {
        $customer = User::factory()->customer()->create([
            'first_name' => 'Layla',
            'last_name' => 'Haddad',
            'name' => 'Layla Haddad',
            'email' => 'layla@example.test',
            'phone' => '+96170111222',
        ]);

        foreach (['Layla', 'Haddad', 'Layla Haddad', 'layla@example.test', '+96170111222'] as $keyword) {
            $data = $this->customerDataTable(['search' => ['value' => $keyword]])
                ->assertOk()
                ->json('data');

            $this->assertCount(1, $data);
            $this->assertSame($customer->email, $data[0]['email']);
        }

        $allData = $this->customerDataTable()->json('data');
        $this->assertCount(1, $allData);
        $this->assertNotContains($this->admin->email, array_column($allData, 'email'));
    }

    public function test_datatable_status_and_verification_filters_work(): void
    {
        User::factory()->customer()->create([
            'email' => 'active-verified@example.test',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        User::factory()->customer()->unverified()->create([
            'email' => 'inactive-unverified@example.test',
            'is_active' => false,
        ]);

        $active = $this->customerDataTable(['status' => 'active'])->json('data');
        $inactive = $this->customerDataTable(['status' => 'inactive'])->json('data');
        $verified = $this->customerDataTable(['verification' => 'verified'])->json('data');
        $unverified = $this->customerDataTable(['verification' => 'unverified'])->json('data');

        $this->assertSame(['active-verified@example.test'], array_column($active, 'email'));
        $this->assertSame(['inactive-unverified@example.test'], array_column($inactive, 'email'));
        $this->assertSame(['active-verified@example.test'], array_column($verified, 'email'));
        $this->assertSame(['inactive-unverified@example.test'], array_column($unverified, 'email'));
    }

    public function test_completed_aggregates_exclude_other_statuses_and_guest_orders(): void
    {
        $customer = User::factory()->customer()->create(['email' => 'buyer@example.test']);
        $this->createOrder($customer, OrderStatus::Completed->value, 125, 'ORD-CUSTOMER-COMPLETE');
        $this->createOrder(
            $customer,
            OrderStatus::Processing->value,
            80,
            'ORD-CUSTOMER-PAID-PROCESSING',
            paymentStatus: PaymentStatus::Paid->value
        );
        $this->createOrder(
            $customer,
            OrderStatus::Cancelled->value,
            70,
            'ORD-CUSTOMER-CANCELLED',
            fulfillmentStatus: FulfillmentStatus::Unfulfilled->value
        );
        $this->createOrder(
            $customer,
            OrderStatus::Cancelled->value,
            60,
            'ORD-CUSTOMER-DELIVERY-FAILED',
            fulfillmentStatus: FulfillmentStatus::DeliveryFailed->value
        );
        $this->createOrder(null, OrderStatus::Completed->value, 900, 'ORD-GUEST-COMPLETE', $customer->email);

        $row = $this->customerDataTable()->json('data.0');

        $this->assertSame(1, $row['completed_orders_count']);
        $this->assertSame('USD 125.00', $row['total_spent']);
    }

    public function test_customers_without_completed_orders_have_zero_aggregates(): void
    {
        User::factory()->customer()->create(['email' => 'no-orders@example.test']);

        $row = $this->customerDataTable()->json('data.0');

        $this->assertSame(0, $row['completed_orders_count']);
        $this->assertSame('USD 0.00', $row['total_spent']);
    }

    public function test_completed_aggregate_ordering_works(): void
    {
        $lower = User::factory()->customer()->create(['email' => 'lower@example.test']);
        $higher = User::factory()->customer()->create(['email' => 'higher@example.test']);
        $this->createOrder($lower, OrderStatus::Completed->value, 10, 'ORD-LOWER');
        $this->createOrder($higher, OrderStatus::Completed->value, 90, 'ORD-HIGHER-1');
        $this->createOrder($higher, OrderStatus::Completed->value, 20, 'ORD-HIGHER-2');

        $response = $this->customerDataTable([
            'order' => [['column' => 4, 'dir' => 'desc']],
        ]);

        $this->assertSame('higher@example.test', $response->json('data.0.email'));
        $this->assertSame('USD 110.00', $response->json('data.0.total_spent'));

        $ascendingTotal = $this->customerDataTable([
            'order' => [['column' => 4, 'dir' => 'asc']],
        ]);
        $descendingCount = $this->customerDataTable([
            'order' => [['column' => 3, 'dir' => 'desc']],
        ]);
        $ascendingCount = $this->customerDataTable([
            'order' => [['column' => 3, 'dir' => 'asc']],
        ]);

        $this->assertSame('lower@example.test', $ascendingTotal->json('data.0.email'));
        $this->assertSame('higher@example.test', $descendingCount->json('data.0.email'));
        $this->assertSame('lower@example.test', $ascendingCount->json('data.0.email'));
    }

    public function test_datatable_escapes_customer_values_once(): void
    {
        User::factory()->customer()->create([
            'first_name' => 'A & B',
            'last_name' => '<script>',
            'name' => 'A & B <script>',
            'email' => 'safe@example.test',
            'phone' => '<b>123</b>',
        ]);

        $row = $this->customerDataTable()->json('data.0');

        $this->assertSame('A &amp; B &lt;script&gt;', $row['name']);
        $this->assertSame('&lt;b&gt;123&lt;/b&gt;', $row['phone']);
        $this->assertStringNotContainsString('&amp;amp;', $row['name']);
    }

    public function test_show_page_displays_customer_empty_address_and_recent_linked_orders(): void
    {
        $customer = User::factory()->customer()->create([
            'name' => 'Customer Detail',
            'email' => 'detail@example.test',
        ]);
        $linked = $this->createOrder(
            $customer,
            OrderStatus::Processing->value,
            40,
            'ORD-LINKED-RECENT'
        );
        $this->createOrder(
            null,
            OrderStatus::Pending->value,
            40,
            'ORD-GUEST-SAME-EMAIL',
            $customer->email
        );

        $this->actingAs($this->admin)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Customer Detail')
            ->assertSee('No default address available.')
            ->assertSee($linked->order_number)
            ->assertDontSee('ORD-GUEST-SAME-EMAIL');
    }

    public function test_show_page_displays_default_address(): void
    {
        $customer = User::factory()->customer()->create();
        $customer->customerAddresses()->create([
            'first_name' => 'Default',
            'last_name' => 'Recipient',
            'address_line_1' => '123 Customer Street',
            'city' => 'Beirut',
            'state' => 'Beirut',
            'country_code' => 'LB',
            'phone' => '70123456',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Default Recipient')
            ->assertSee('123 Customer Street');
    }

    public function test_status_ajax_response_remains_available_from_ui(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($this->admin)
            ->patchJson(route('admin.customers.status.update', $customer), [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJson([
                'is_active' => false,
            ]);
    }

    private function customerDataTable(array $overrides = [])
    {
        $columns = collect([
            'name',
            'email',
            'phone',
            'completed_orders_count',
            'total_spent',
            'is_active',
            'created_at',
            'actions',
        ])->map(fn (string $name) => [
            'data' => $name,
            'name' => $name,
            'searchable' => ! in_array($name, ['completed_orders_count', 'total_spent', 'is_active', 'created_at', 'actions'], true),
            'orderable' => $name !== 'actions',
            'search' => ['value' => '', 'regex' => false],
        ])->map(function (array $column): array {
            $column['searchable'] = $column['searchable'] ? 'true' : 'false';
            $column['orderable'] = $column['orderable'] ? 'true' : 'false';
            $column['search']['regex'] = 'false';

            return $column;
        })->all();

        $parameters = array_replace_recursive([
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
            'columns' => $columns,
        ], $overrides);

        return $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.customers.index').'?'.http_build_query($parameters));
    }

    private function createOrder(
        ?User $customer,
        string $status,
        float $grandTotal,
        string $orderNumber,
        ?string $email = null,
        ?string $paymentStatus = null,
        ?string $fulfillmentStatus = null
    ): Order {
        return Order::create([
            'order_number' => $orderNumber,
            'user_id' => $customer?->id,
            'customer_email' => $email ?? $customer?->email ?? 'guest@example.test',
            'customer_first_name' => 'Test',
            'customer_last_name' => 'Customer',
            'customer_phone' => null,
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => $status,
            'payment_status' => $paymentStatus ?? ($status === OrderStatus::Completed->value
                ? PaymentStatus::Paid->value
                : PaymentStatus::Pending->value),
            'fulfillment_status' => $fulfillmentStatus ?? ($status === OrderStatus::Completed->value
                ? FulfillmentStatus::Fulfilled->value
                : FulfillmentStatus::Unfulfilled->value),
            'payment_method' => 'cash_on_delivery',
            'requires_payment_before_processing' => false,
            'subtotal' => $grandTotal,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => $grandTotal,
            'placed_at' => now(),
        ]);
    }
}
