<?php

namespace App\Presenters;

use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;

class OrderPrintPresenter
{
    public function __construct(private StorePrintIdentityPresenter $storeIdentity) {}

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

    /** @return array<string, mixed> */
    public function present(Order $order): array
    {
        $order->load([
            'billingAddress',
            'shippingAddress',
            'shipping',
            'payment',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.options' => fn ($query) => $query
                ->orderBy('attribute_code')
                ->orderBy('id'),
            'refunds' => fn ($query) => $query
                ->latest('refunded_at')
                ->latest('id'),
        ]);

        $children = $order->items->groupBy('parent_order_item_id');
        $rootItems = $order->items
            ->whereNull('parent_order_item_id')
            ->each(fn (OrderItem $item) => $item->setRelation(
                'children',
                $children->get($item->getKey(), collect())->values()
            ))
            ->values();

        return [
            'order' => $order,
            'rootItems' => $rootItems,
            'billingAddress' => $order->billingAddress,
            'shippingAddress' => $order->shippingAddress,
            'shippingMatchesBilling' => $this->addressesMatch(
                $order->billingAddress,
                $order->shippingAddress
            ),
            'store' => $this->storeIdentity->present(),
        ];
    }

    private function addressesMatch(?OrderAddress $billing, ?OrderAddress $shipping): bool
    {
        if (! $billing || ! $shipping) {
            return false;
        }

        foreach (self::ADDRESS_FIELDS as $field) {
            if ((string) $billing->{$field} !== (string) $shipping->{$field}) {
                return false;
            }
        }

        return true;
    }
}
