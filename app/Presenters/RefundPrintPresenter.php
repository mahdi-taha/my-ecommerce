<?php

namespace App\Presenters;

use App\Models\Refund;

class RefundPrintPresenter
{
    public function __construct(private StorePrintIdentityPresenter $storeIdentity) {}

    /** @return array<string, mixed> */
    public function present(Refund $refund): array
    {
        $refund->load([
            'order',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.orderItem',
            'items.orderItem.options' => fn ($query) => $query
                ->orderBy('attribute_code')
                ->orderBy('id'),
        ]);

        return [
            'refund' => $refund,
            'order' => $refund->order,
            'refundItems' => $refund->items,
            'refundStatus' => 'Completed',
            'store' => $this->storeIdentity->present(),
        ];
    }
}
