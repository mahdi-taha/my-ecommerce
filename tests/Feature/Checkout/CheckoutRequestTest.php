<?php

namespace Tests\Feature\Checkout;

use App\Http\Requests\CheckoutRequest;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CheckoutRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/testing/checkout-foundation', function (CheckoutRequest $request) {
            return response()->json($request->validated());
        });
    }

    public function test_structurally_valid_checkout_data_is_accepted(): void
    {
        $shippingMethod = ShippingMethod::factory()->create(['is_active' => true]);
        $paymentMethod = PaymentMethod::factory()->create(['is_active' => true]);

        $this->postJson('/testing/checkout-foundation', $this->payload(
            $shippingMethod->code,
            $paymentMethod->code
        ))
            ->assertOk()
            ->assertJsonPath('address_source', 'manual')
            ->assertJsonPath('manual_address.country_code', 'LB');
    }

    public function test_shipping_and_payment_methods_must_be_active(): void
    {
        $shippingMethod = ShippingMethod::factory()->create(['is_active' => false]);
        $paymentMethod = PaymentMethod::factory()->create(['is_active' => false]);

        $this->postJson('/testing/checkout-foundation', $this->payload(
            $shippingMethod->code,
            $paymentMethod->code
        ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shipping_method', 'payment_method']);
    }

    public function test_customer_contact_and_manual_address_are_required(): void
    {
        $shippingMethod = ShippingMethod::factory()->create(['is_active' => true]);
        $paymentMethod = PaymentMethod::factory()->create(['is_active' => true]);
        $payload = $this->payload($shippingMethod->code, $paymentMethod->code);
        unset(
            $payload['customer']['first_name'],
            $payload['customer']['phone'],
            $payload['manual_address']['address_line_1'],
            $payload['manual_address']['city']
        );

        $this->postJson('/testing/checkout-foundation', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer.first_name',
                'customer.phone',
                'manual_address.address_line_1',
                'manual_address.city',
            ]);
    }

    public function test_email_is_optional_and_country_codes_are_normalized(): void
    {
        $shippingMethod = ShippingMethod::factory()->create(['is_active' => true]);
        $paymentMethod = PaymentMethod::factory()->create(['is_active' => true]);
        $payload = $this->payload($shippingMethod->code, $paymentMethod->code);
        $payload['customer']['email'] = '   ';
        $payload['manual_address']['country_code'] = 'lb';

        $this->postJson('/testing/checkout-foundation', $payload)
            ->assertOk()
            ->assertJsonPath('customer.email', null)
            ->assertJsonPath('manual_address.country_code', 'LB');
    }

    public function test_browser_supplied_customer_identity_fields_are_prohibited(): void
    {
        $shippingMethod = ShippingMethod::factory()->create(['is_active' => true]);
        $paymentMethod = PaymentMethod::factory()->create(['is_active' => true]);
        $payload = $this->payload($shippingMethod->code, $paymentMethod->code);
        $payload['customer']['user_id'] = 99;
        $payload['customer']['account_type'] = 'admin';

        $this->postJson('/testing/checkout-foundation', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer.user_id', 'customer.account_type']);
    }

    private function payload(string $shippingMethod, string $paymentMethod): array
    {
        return [
            'shipping_method' => $shippingMethod,
            'payment_method' => $paymentMethod,
            'customer' => [
                'first_name' => 'Guest',
                'last_name' => 'Customer',
                'phone' => '70123456',
                'email' => 'guest@example.com',
            ],
            'address_source' => 'manual',
            'manual_address' => $this->address(),
        ];
    }

    private function address(): array
    {
        return [
            'first_name' => 'Guest',
            'last_name' => 'Customer',
            'company' => null,
            'email' => 'guest@example.com',
            'phone' => '70123456',
            'address_line_1' => 'Test Street',
            'address_line_2' => null,
            'city' => 'Beirut',
            'state' => null,
            'postal_code' => null,
            'country_code' => 'LB',
        ];
    }
}
