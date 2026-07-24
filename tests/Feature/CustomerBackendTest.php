<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerBackendTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_active_admin_can_access_customer_routes(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.customers.index'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('admin.customers.create'))
            ->assertOk();
    }

    public function test_customer_binding_resolves_only_customer_records(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee($customer->name);
    }

    public function test_admin_id_returns_not_found_on_customer_routes(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.customers.show', $this->admin))
            ->assertNotFound();
    }

    public function test_unknown_customer_id_returns_not_found(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.customers.show', 999999))
            ->assertNotFound();
    }

    public function test_customer_creation_forces_type_verifies_email_and_normalizes_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.customers.store'), $this->customerData([
                'first_name' => '  Jane ',
                'last_name' => ' Doe  ',
                'email' => '  jane@example.test ',
                'phone' => '  +961 70 123 456  ',
            ]));

        $customer = User::customers()->where('email', 'jane@example.test')->firstOrFail();

        $response->assertRedirect(route('admin.customers.edit', $customer));
        $this->assertSame(AccountType::Customer, $customer->account_type);
        $this->assertSame('Jane', $customer->first_name);
        $this->assertSame('Doe', $customer->last_name);
        $this->assertSame('Jane Display', $customer->name);
        $this->assertSame('+961 70 123 456', $customer->phone);
        $this->assertNotNull($customer->email_verified_at);
        $this->assertTrue(Hash::check('secure123', $customer->password));
    }

    public function test_blank_phone_is_stored_as_null(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.customers.store'), $this->customerData([
                'email' => 'blank-phone@example.test',
                'phone' => '   ',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(
            User::customers()->where('email', 'blank-phone@example.test')->value('phone')
        );
    }

    public function test_manual_customer_can_be_created_without_credentials(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.customers.store'), $this->customerData([
                'name' => 'Walk-in Customer',
                'email' => null,
                'password' => null,
                'password_confirmation' => null,
                'has_account' => false,
            ]))
            ->assertSessionHasNoErrors();

        $customer = User::manualCustomers()->where('name', 'Walk-in Customer')->firstOrFail();
        $this->assertNull($customer->email);
        $this->assertNull($customer->password);
        $this->assertNull($customer->email_verified_at);
    }

    public function test_customer_can_be_created_inactive(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.customers.store'), $this->customerData([
                'email' => 'inactive-customer@example.test',
                'is_active' => false,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertFalse(
            User::customers()->where('email', 'inactive-customer@example.test')->firstOrFail()->is_active
        );
    }

    public function test_customer_email_must_be_unique(): void
    {
        User::factory()->customer()->create(['email' => 'duplicate@example.test']);

        $this->actingAs($this->admin)
            ->post(route('admin.customers.store'), $this->customerData([
                'email' => 'duplicate@example.test',
            ]))
            ->assertSessionHasErrors('email');
    }

    public function test_customer_phone_may_be_shared(): void
    {
        User::factory()->customer()->create(['phone' => '+96170123456']);

        $this->actingAs($this->admin)
            ->post(route('admin.customers.store'), $this->customerData([
                'email' => 'other@example.test',
                'phone' => ' +96170123456 ',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_customer_update_changes_identity_without_changing_password(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'before@example.test',
            'password' => 'original123',
        ]);
        $password = $customer->password;

        $response = $this->actingAs($this->admin)
            ->put(route('admin.customers.update', $customer), [
                'name' => 'Independent Display Name',
                'first_name' => '  Updated ',
                'last_name' => ' Customer ',
                'email' => ' updated@example.test ',
                'phone' => '   ',
                'is_active' => true,
            ]);

        $customer->refresh();

        $response->assertRedirect(route('admin.customers.edit', $customer));
        $this->assertSame('Independent Display Name', $customer->name);
        $this->assertSame('updated@example.test', $customer->email);
        $this->assertNull($customer->phone);
        $this->assertSame($password, $customer->password);
    }

    public function test_account_type_cannot_be_submitted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.customers.store'), $this->customerData([
                'account_type' => 'admin',
            ]))
            ->assertSessionHasErrors('account_type');
    }

    public function test_account_type_and_password_cannot_be_changed_during_normal_update(): void
    {
        $customer = User::factory()->customer()->create([
            'password' => 'original123',
        ]);
        $password = $customer->password;

        $this->actingAs($this->admin)
            ->put(route('admin.customers.update', $customer), [
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'is_active' => true,
                'account_type' => 'admin',
                'password' => 'changed123',
                'password_confirmation' => 'changed123',
            ])
            ->assertSessionHasErrors(['account_type', 'password', 'password_confirmation']);

        $customer->refresh();
        $this->assertSame(AccountType::Customer, $customer->account_type);
        $this->assertSame($password, $customer->password);
    }

    public function test_customer_password_can_be_updated_separately(): void
    {
        $customer = User::factory()->customer()->create([
            'password' => 'original123',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.customers.password.update', $customer), [
                'password' => 'changed123',
                'password_confirmation' => 'changed123',
            ])
            ->assertRedirect(route('admin.customers.edit', $customer));

        $password = $customer->fresh()->password;
        $this->assertFalse(Hash::check('original123', $password));
        $this->assertTrue(Hash::check('changed123', $password));
    }

    public function test_manual_customer_cannot_use_password_management(): void
    {
        $customer = User::factory()->manualCustomer()->create();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.customers.password.edit', $customer))
            ->assertNotFound();

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.customers.password.update', $customer), [
                'password' => 'changed123',
                'password_confirmation' => 'changed123',
            ])
            ->assertSessionHasErrors('customer');

        $this->assertNull($customer->fresh()->password);
    }

    public function test_customer_password_requires_confirmation_and_strength(): void
    {
        $customer = User::factory()->customer()->create([
            'password' => 'original123',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.customers.password.update', $customer), [
                'password' => 'weak',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('original123', $customer->fresh()->password));
    }

    public function test_account_type_is_prohibited_from_password_and_status_actions(): void
    {
        $customer = User::factory()->customer()->create([
            'password' => 'original123',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.customers.password.update', $customer), [
                'password' => 'changed123',
                'password_confirmation' => 'changed123',
                'account_type' => 'admin',
            ])
            ->assertSessionHasErrors('account_type');

        $this->actingAs($this->admin)
            ->patchJson(route('admin.customers.status.update', $customer), [
                'is_active' => false,
                'account_type' => 'admin',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('account_type');

        $customer->refresh();
        $this->assertSame(AccountType::Customer, $customer->account_type);
        $this->assertTrue($customer->is_active);
        $this->assertTrue(Hash::check('original123', $customer->password));
    }

    public function test_status_update_supports_json_and_non_json_responses(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($this->admin)
            ->patchJson(route('admin.customers.status.update', $customer), [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJson([
                'message' => 'Customer status updated successfully.',
                'is_active' => false,
            ]);

        $this->actingAs($this->admin)
            ->from(route('admin.customers.edit', $customer))
            ->patch(route('admin.customers.status.update', $customer), [
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.customers.edit', $customer))
            ->assertSessionHas('success');

        $this->assertTrue($customer->fresh()->is_active);
    }

    public function test_customer_service_rejects_non_customer_records(): void
    {
        $this->expectException(ValidationException::class);

        app(CustomerService::class)->updateStatus($this->admin, false);
    }

    public function test_failed_customer_update_rolls_back_all_changes(): void
    {
        User::factory()->customer()->create([
            'email' => 'taken@example.test',
        ]);
        $customer = User::factory()->customer()->create([
            'first_name' => 'Original',
            'last_name' => 'Name',
            'name' => 'Original Name',
            'email' => 'original@example.test',
        ]);

        try {
            app(CustomerService::class)->update($customer, [
                'name' => 'Changed Display',
                'first_name' => 'Changed',
                'last_name' => 'Name',
                'email' => 'taken@example.test',
                'phone' => null,
                'is_active' => false,
            ]);
            $this->fail('Expected a unique constraint violation.');
        } catch (QueryException) {
            $customer->refresh();
            $this->assertSame('Original Name', $customer->name);
            $this->assertSame('original@example.test', $customer->email);
            $this->assertTrue($customer->is_active);
        }
    }

    private function customerData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Display',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'customer@example.test',
            'phone' => null,
            'password' => 'secure123',
            'password_confirmation' => 'secure123',
            'is_active' => true,
            'has_account' => true,
        ], $overrides);
    }
}
