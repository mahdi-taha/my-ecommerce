<?php

namespace Tests\Unit\Services;

use App\Enums\ShippingMethodType;
use App\Models\ShippingMethod;
use App\Services\CheckoutService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CheckoutServiceTest extends TestCase
{
    public function test_it_prepares_address_shipping_and_tax_snapshots_without_persistence(): void
    {
        $service = new CheckoutService;
        $shippingMethod = new ShippingMethod([
            'code' => 'inside_beirut',
            'name' => 'Inside Beirut',
            'type' => ShippingMethodType::Delivery,
            'amount' => '2.0000',
        ]);
        $shippingMethod->setAttribute('id', 7);

        $this->assertSame([
            'first_name' => 'Guest',
            'city' => 'Beirut',
            'type' => 'billing',
        ], $service->prepareAddressSnapshot([
            'first_name' => 'Guest',
            'city' => 'Beirut',
            'untrusted' => 'ignored',
        ], 'billing'));

        $this->assertSame([
            'shipping_method_id' => 7,
            'shipping_method_code' => 'inside_beirut',
            'shipping_method_name' => 'Inside Beirut',
            'shipping_method_type' => 'delivery',
            'shipping_amount' => '2.0000',
        ], $service->prepareShippingSnapshot($shippingMethod));

        $this->assertSame([
            'tax_name' => 'Standard Tax',
            'tax_rate' => '11.0000',
            'tax_amount' => '1.1000',
        ], $service->prepareTaxSnapshot('Standard Tax', '11.0000', '1.1000'));
    }

    public function test_option_snapshots_are_normalized_and_ordered_by_attribute_code(): void
    {
        $snapshots = (new CheckoutService)->prepareOptionSnapshots([
            [
                'attribute_code' => ' size ',
                'attribute_name' => ' Size ',
                'option_code' => ' xl ',
                'option_label' => ' XL ',
            ],
            [
                'attribute_code' => ' color ',
                'attribute_name' => ' Color ',
                'option_code' => ' black ',
                'option_label' => ' Black ',
            ],
        ]);

        $this->assertSame(['color', 'size'], array_column($snapshots, 'attribute_code'));
        $this->assertSame('Black', $snapshots[0]['option_label']);
    }

    public function test_invalid_address_type_and_incomplete_options_are_rejected(): void
    {
        $service = new CheckoutService;

        try {
            $service->prepareAddressSnapshot([], 'pickup');
            $this->fail('An invalid address snapshot type was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The Order address snapshot type must be billing or shipping.',
                $exception->getMessage()
            );
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Order item option option_label snapshot is required.');

        $service->prepareOptionSnapshots([[
            'attribute_code' => 'color',
            'attribute_name' => 'Color',
            'option_code' => 'black',
            'option_label' => '',
        ]]);
    }
}
