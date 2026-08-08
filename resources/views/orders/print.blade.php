<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('shop.order_print.title') }} {{ $order->order_number }}</title>
    @vite('resources/css/order-print.css')
</head>
<body>
    <main class="order-print-document">
        <div class="order-print-actions">
            <button type="button" onclick="window.print()">{{ __('shop.order_print.print') }}</button>
        </div>

        <header class="order-print-header">
            <div>
                @if ($store['logo_url'])
                    <img class="order-print-logo" src="{{ $store['logo_url'] }}"
                        alt="{{ __('shop.topbar.store_logo', ['store' => $store['name']]) }}">
                @endif
                <h1>{{ $store['name'] }}</h1>
                @if ($store['address'] !== '')<div>{{ $store['address'] }}</div>@endif
                @if ($store['email'] !== '')<div><bdi>{{ $store['email'] }}</bdi></div>@endif
                @if ($store['phone'] !== '')<div><bdi>{{ $store['phone'] }}</bdi></div>@endif
            </div>
            <div class="order-print-heading">
                <h2>{{ __('shop.order_print.order_summary') }}</h2>
                <strong><bdi>{{ $order->order_number }}</bdi></strong>
            </div>
        </header>

        <section class="order-print-section order-print-facts" aria-labelledby="order-details-heading">
            <h2 id="order-details-heading">{{ __('shop.order_print.order_details') }}</h2>
            <dl>
                <div><dt>{{ __('shop.order_print.placed_at') }}</dt><dd><bdi>{{ $order->placed_at?->format('Y-m-d H:i') }}</bdi></dd></div>
                <div><dt>{{ __('shop.order_print.order_status') }}</dt><dd>{{ __('shop.checkout.status.order.'.$order->status) }}</dd></div>
                <div><dt>{{ __('shop.order_print.payment_status') }}</dt><dd>{{ __('shop.checkout.status.payment.'.$order->payment_status) }}</dd></div>
                <div><dt>{{ __('shop.order_print.fulfillment_status') }}</dt><dd>{{ __('shop.checkout.status.fulfillment.'.$order->fulfillment_status) }}</dd></div>
                <div><dt>{{ __('shop.order_print.currency') }}</dt><dd><bdi>{{ $order->currency_code }}</bdi></dd></div>
            </dl>
        </section>

        <section class="order-print-section" aria-labelledby="customer-heading">
            <h2 id="customer-heading">{{ __('shop.order_print.customer') }}</h2>
            <p>
                <strong>{{ trim($order->customer_first_name.' '.$order->customer_last_name) }}</strong><br>
                @if ($order->customer_email)<bdi>{{ $order->customer_email }}</bdi><br>@endif
                @if ($order->customer_phone)<bdi>{{ $order->customer_phone }}</bdi>@endif
            </p>
        </section>

        <div class="order-print-addresses">
            @foreach ([
                ['heading' => __('shop.order_print.billing_address'), 'address' => $billingAddress, 'same' => false],
                ['heading' => __('shop.order_print.shipping_address'), 'address' => $shippingAddress, 'same' => $shippingMatchesBilling],
            ] as $block)
                <section class="order-print-section">
                    <h2>{{ $block['heading'] }}</h2>
                    @if ($block['same'])
                        <p>{{ __('shop.order_print.same_as_billing') }}</p>
                    @elseif ($block['address'])
                        <address>
                            <strong>{{ $block['address']->first_name }} {{ $block['address']->last_name }}</strong><br>
                            @if ($block['address']->company){{ $block['address']->company }}<br>@endif
                            {{ $block['address']->address_line_1 }}<br>
                            @if ($block['address']->address_line_2){{ $block['address']->address_line_2 }}<br>@endif
                            {{ $block['address']->city }}@if ($block['address']->state), {{ $block['address']->state }}@endif
                            @if ($block['address']->postal_code) <bdi>{{ $block['address']->postal_code }}</bdi>@endif<br>
                            <bdi>{{ $block['address']->country_code }}</bdi><br>
                            @if ($block['address']->email)<bdi>{{ $block['address']->email }}</bdi><br>@endif
                            @if ($block['address']->phone)<bdi>{{ $block['address']->phone }}</bdi>@endif
                        </address>
                    @else
                        <p>—</p>
                    @endif
                </section>
            @endforeach
        </div>

        <section class="order-print-section" aria-labelledby="items-heading">
            <h2 id="items-heading">{{ __('shop.order_print.items') }}</h2>
            <div class="order-print-table-wrap">
                <table class="order-print-items">
                    <thead><tr>
                        <th>{{ __('shop.order_print.product') }}</th>
                        <th>{{ __('shop.order_print.sku') }}</th>
                        <th class="number">{{ __('shop.order_print.quantity') }}</th>
                        <th class="number">{{ __('shop.order_print.unit_price') }}</th>
                        <th class="number">{{ __('shop.order_print.subtotal') }}</th>
                        <th class="number">{{ __('shop.order_print.discount') }}</th>
                        <th class="number">{{ __('shop.order_print.tax') }}</th>
                        <th class="number">{{ __('shop.order_print.line_total') }}</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($rootItems as $item)
                            @php($contextOnly = $item->children->isNotEmpty() && ! $item->isFinanciallyRefundable())
                            @if ($contextOnly)
                                <tr class="order-print-context-row">
                                    <td colspan="8"><strong>{{ $item->name }}</strong> <bdi>{{ $item->sku }}</bdi></td>
                                </tr>
                            @endif
                            @php($printRows = $contextOnly ? $item->children : collect([$item])->concat($item->children))
                            @foreach ($printRows as $rowItem)
                                <tr class="{{ $rowItem->parent_order_item_id ? 'order-print-child-row' : '' }}">
                                    <td>
                                        <strong>{{ $rowItem->name }}</strong>
                                        @forelse ($rowItem->options as $option)
                                            <small>{{ $option->attribute_name }}: {{ $option->option_label }}</small>
                                        @empty
                                            @if ($rowItem->option_summary)<small>{{ $rowItem->option_summary }}</small>@endif
                                        @endforelse
                                    </td>
                                    <td><bdi>{{ $rowItem->sku }}</bdi></td>
                                    <td class="number"><bdi>{{ rtrim(rtrim(number_format((float) $rowItem->quantity, 4, '.', ''), '0'), '.') }}</bdi></td>
                                    <td class="number"><bdi>{{ format_store_price($rowItem->unit_price, $order->currency_code) }}</bdi></td>
                                    <td class="number"><bdi>{{ format_store_price($rowItem->row_subtotal, $order->currency_code) }}</bdi></td>
                                    <td class="number"><bdi>{{ format_store_price($rowItem->discount_amount, $order->currency_code) }}</bdi></td>
                                    <td class="number"><bdi>{{ format_store_price($rowItem->tax_amount, $order->currency_code) }}</bdi></td>
                                    <td class="number"><bdi>{{ format_store_price($rowItem->row_total, $order->currency_code) }}</bdi></td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="8">{{ __('shop.order_print.no_items') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="order-print-summary-grid">
            <section class="order-print-section">
                <h2>{{ __('shop.order_print.payment') }}</h2>
                <dl class="order-print-compact-list">
                    <div><dt>{{ __('shop.order_print.method') }}</dt><dd>{{ $order->payment?->method_name ?? $order->payment_method }}</dd></div>
                    <div><dt>{{ __('shop.order_print.status') }}</dt><dd>{{ __('shop.checkout.status.payment.'.$order->payment_status) }}</dd></div>
                    @if ((float) ($order->payment?->paid_amount ?? 0) > 0)
                        <div><dt>{{ __('shop.order_print.amount_paid') }}</dt><dd><bdi>{{ format_store_price($order->payment->paid_amount, $order->payment->currency_code) }}</bdi></dd></div>
                    @endif
                </dl>
            </section>
            <section class="order-print-section">
                <h2>{{ __('shop.order_print.shipping') }}</h2>
                <dl class="order-print-compact-list">
                    <div><dt>{{ __('shop.order_print.method') }}</dt><dd>{{ $order->shipping?->shipping_method_name ?? '—' }}</dd></div>
                    @if ($order->shipping?->shipping_method_type)
                        <div><dt>{{ __('shop.order_print.type') }}</dt><dd>{{ $order->shipping->shipping_method_type }}</dd></div>
                    @endif
                    <div><dt>{{ __('shop.order_print.amount') }}</dt><dd><bdi>{{ format_store_price($order->shipping?->shipping_amount ?? $order->shipping_total, $order->currency_code) }}</bdi></dd></div>
                </dl>
            </section>
        </div>

        @if ($order->refunds->isNotEmpty())
            <section class="order-print-section" aria-labelledby="refunds-heading">
                <h2 id="refunds-heading">{{ __('shop.order_print.refunds') }}</h2>
                <div class="order-print-table-wrap"><table>
                    <thead><tr>
                        <th>{{ __('shop.order_print.refund_number') }}</th>
                        <th>{{ __('shop.order_print.date') }}</th>
                        <th class="number">{{ __('shop.order_print.merchandise_refund') }}</th>
                        <th class="number">{{ __('shop.order_print.shipping_deduction') }}</th>
                        <th class="number">{{ __('shop.order_print.customer_refund') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($order->refunds as $refund)
                            <tr>
                                <td><bdi>{{ $refund->refund_number }}</bdi></td>
                                <td><bdi>{{ $refund->refunded_at?->format('Y-m-d H:i') }}</bdi></td>
                                <td class="number"><bdi>{{ format_store_price($refund->merchandise_amount, $refund->currency_code) }}</bdi></td>
                                <td class="number"><bdi>{{ (float) $refund->shipping_deduction > 0 ? format_store_price($refund->shipping_deduction, $refund->currency_code) : '—' }}</bdi></td>
                                <td class="number"><bdi>{{ format_store_price($refund->customer_refund_amount, $refund->currency_code) }}</bdi></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            </section>
        @endif

        <section class="order-print-section order-print-totals" aria-labelledby="totals-heading">
            <h2 id="totals-heading">{{ __('shop.order_print.totals') }}</h2>
            <dl>
                <div><dt>{{ __('shop.order_print.subtotal') }}</dt><dd><bdi>{{ format_store_price($order->subtotal, $order->currency_code) }}</bdi></dd></div>
                <div><dt>{{ __('shop.order_print.discount') }}</dt><dd><bdi>{{ format_store_price($order->discount_total, $order->currency_code) }}</bdi></dd></div>
                <div><dt>{{ __('shop.order_print.shipping') }}</dt><dd><bdi>{{ format_store_price($order->shipping_total, $order->currency_code) }}</bdi></dd></div>
                <div><dt>{{ __('shop.order_print.tax') }}</dt><dd><bdi>{{ format_store_price($order->tax_total, $order->currency_code) }}</bdi></dd></div>
                <div class="grand-total"><dt>{{ __('shop.order_print.grand_total') }}</dt><dd><bdi>{{ format_store_price($order->grand_total, $order->currency_code) }}</bdi></dd></div>
            </dl>
        </section>
    </main>
</body>
</html>
