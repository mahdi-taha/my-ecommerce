<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_login_renders_english_translations(): void
    {
        $this->get(route('customer.login', ['locale' => 'en']))
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee(__('shop.auth.login.title'))
            ->assertSee(__('shop.auth.login.remember'));
    }

    public function test_customer_login_and_password_pages_render_arabic_with_rtl_direction(): void
    {
        app()->setLocale('ar');
        $customer = User::factory()->customer()->create();

        $this->get(route('customer.login', ['locale' => 'ar']))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee(__('shop.auth.login.title'))
            ->assertSee(__('shop.auth.login.email'))
            ->assertSee(__('shop.auth.login.password'));

        $this->actingAs($customer, 'customer')
            ->get(route('customer.account.password.edit', ['locale' => 'ar']))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee(__('shop.account.password.current_password'))
            ->assertSee(__('shop.account.password.new_password'))
            ->assertSee(__('shop.account.password.confirm_password'));
    }

    public function test_customer_login_failure_and_lockout_use_the_localized_generic_message(): void
    {
        app()->setLocale('ar');
        $customer = User::factory()->customer()->create([
            'email' => 'localized-customer@example.test',
            'password' => 'password123',
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('customer.login.store', ['locale' => 'ar']), [
                'email' => $customer->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors([
                'email' => __('shop.auth.login.invalid_credentials'),
            ]);
        }

        $this->post(route('customer.login.store', ['locale' => 'ar']), [
            'email' => $customer->email,
            'password' => 'password123',
        ])->assertSessionHasErrors([
            'email' => __('shop.auth.login.invalid_credentials'),
        ]);
    }

    public function test_customer_validation_messages_are_localized_in_arabic(): void
    {
        app()->setLocale('ar');

        $this->post(route('customer.login.store', ['locale' => 'ar']), [])
            ->assertSessionHasErrors([
                'email' => __('validation.required', [
                    'attribute' => __('validation.attributes.email'),
                ]),
                'password' => __('validation.required', [
                    'attribute' => __('validation.attributes.password'),
                ]),
            ]);
    }

    public function test_password_update_success_message_is_localized(): void
    {
        app()->setLocale('ar');
        $customer = User::factory()->customer()->create(['password' => 'original123']);

        $this->actingAs($customer, 'customer')
            ->put(route('customer.account.password.update', ['locale' => 'ar']), [
                'current_password' => 'original123',
                'password' => 'replacement123',
                'password_confirmation' => 'replacement123',
            ])
            ->assertSessionHas('success', __('shop.account.password.updated'));
    }

    public function test_administrator_login_failure_remains_english(): void
    {
        app()->setLocale('ar');

        $this->post(route('admin.login.store'), [
            'email' => 'missing-admin@example.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors([
            'email' => 'The provided credentials are invalid.',
        ]);
    }
}
