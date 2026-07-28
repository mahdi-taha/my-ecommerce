<?php

namespace App\Services;

use App\Models\ShippingMethod;
use InvalidArgumentException;

class CheckoutService
{
    private const ADDRESS_FIELDS = [
        'first_name',
        'last_name',
        'company',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country_code',
    ];

    public function prepareAddressSnapshot(array $address, string $type): array
    {
        if (! in_array($type, ['billing', 'shipping'], true)) {
            throw new InvalidArgumentException('The Order address snapshot type must be billing or shipping.');
        }

        return array_merge(
            array_intersect_key($address, array_flip(self::ADDRESS_FIELDS)),
            ['type' => $type]
        );
    }

    public function prepareShippingSnapshot(ShippingMethod $shippingMethod): array
    {
        return [
            'shipping_method_id' => $shippingMethod->getKey(),
            'shipping_method_code' => $shippingMethod->code,
            'shipping_method_name' => $shippingMethod->name,
            'shipping_method_type' => $shippingMethod->type->value,
            'shipping_amount' => $shippingMethod->amount,
        ];
    }

    public function prepareTaxSnapshot(?string $taxName, string $taxRate, string $taxAmount): array
    {
        return [
            'tax_name' => $taxName,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
        ];
    }

    public function prepareOptionSnapshots(array $options): array
    {
        return collect($options)
            ->map(function (array $option): array {
                foreach (['attribute_code', 'attribute_name', 'option_code', 'option_label'] as $field) {
                    if (! isset($option[$field]) || trim((string) $option[$field]) === '') {
                        throw new InvalidArgumentException("The Order item option {$field} snapshot is required.");
                    }
                }

                return [
                    'attribute_code' => trim((string) $option['attribute_code']),
                    'attribute_name' => trim((string) $option['attribute_name']),
                    'option_code' => trim((string) $option['option_code']),
                    'option_label' => trim((string) $option['option_label']),
                ];
            })
            ->sortBy('attribute_code')
            ->values()
            ->all();
    }
}
