<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('shop.refund_print.title') }} {{ $refund->refund_number }}</title>
    @vite('resources/css/refund-print.css')
</head>
<body>
    <main class="refund-print-document">
        <div class="refund-print-actions">
            <button type="button" onclick="window.print()">{{ __('shop.refund_print.print') }}</button>
        </div>

        <header class="refund-print-header">
            <div>
                @if ($store['logo_url'])
                    <img class="refund-print-logo" src="{{ $store['logo_url'] }}"
                        alt="{{ __('shop.topbar.store_logo', ['store' => $store['name']]) }}">
                @endif
                <h1>{{ $store['name'] }}</h1>
                @if ($store['address'] !== '')<div>{{ $store['address'] }}</div>@endif
                @if ($store['email'] !== '')<div><bdi>{{ $store['email'] }}</bdi></div>@endif
                @if ($store['phone'] !== '')<div><bdi>{{ $store['phone'] }}</bdi></div>@endif
            </div>
            <div class="refund-print-heading">
                <h2>{{ __('shop.refund_print.refund_summary') }}</h2>
                <strong><bdi>{{ $refund->refund_number }}</bdi></strong>
            </div>
        </header>

        <section class="refund-print-section refund-print-facts" aria-labelledby="refund-details-heading">
            <h2 id="refund-details-heading">{{ __('shop.refund_print.refund_details') }}</h2>
            <dl>
                <div><dt>{{ __('shop.refund_print.refund_number') }}</dt><dd><bdi>{{ $refund->refund_number }}</bdi></dd></div>
                <div><dt>{{ __('shop.refund_print.refunded_at') }}</dt><dd><bdi>{{ $refund->refunded_at?->format('Y-m-d H:i') }}</bdi></dd></div>
                <div><dt>{{ __('shop.refund_print.status') }}</dt><dd>{{ $refundStatus }}</dd></div>
                <div><dt>{{ __('shop.refund_print.currency') }}</dt><dd><bdi>{{ $refund->currency_code }}</bdi></dd></div>
            </dl>
        </section>

        <div class="refund-print-summary-grid">
            <section class="refund-print-section">
                <h2>{{ __('shop.refund_print.original_order') }}</h2>
                <dl class="refund-print-compact-list">
                    <div><dt>{{ __('shop.refund_print.order_number') }}</dt><dd><bdi>{{ $order->order_number }}</bdi></dd></div>
                    <div><dt>{{ __('shop.refund_print.order_date') }}</dt><dd><bdi>{{ $order->placed_at?->format('Y-m-d H:i') }}</bdi></dd></div>
                </dl>
            </section>
            <section class="refund-print-section">
                <h2>{{ __('shop.refund_print.customer') }}</h2>
                <p>
                    <strong>{{ trim($order->customer_first_name.' '.$order->customer_last_name) }}</strong><br>
                    @if ($order->customer_email)<bdi>{{ $order->customer_email }}</bdi><br>@endif
                    @if ($order->customer_phone)<bdi>{{ $order->customer_phone }}</bdi>@endif
                </p>
            </section>
        </div>

        <section class="refund-print-section" aria-labelledby="refunded-items-heading">
            <h2 id="refunded-items-heading">{{ __('shop.refund_print.refunded_items') }}</h2>
            <div class="refund-print-table-wrap">
                <table class="refund-print-items">
                    <thead><tr>
                        <th>{{ __('shop.refund_print.product') }}</th>
                        <th>{{ __('shop.refund_print.sku') }}</th>
                        <th class="number">{{ __('shop.refund_print.quantity') }}</th>
                        <th class="number">{{ __('shop.refund_print.unit_price') }}</th>
                        <th class="number">{{ __('shop.refund_print.refunded_amount') }}</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($refundItems as $refundItem)
                            <tr>
                                <td>
                                    <strong>{{ $refundItem->orderItem->name }}</strong>
                                    @forelse ($refundItem->orderItem->options as $option)
                                        <small>{{ $option->attribute_name }}: {{ $option->option_label }}</small>
                                    @empty
                                        @if ($refundItem->orderItem->option_summary)<small>{{ $refundItem->orderItem->option_summary }}</small>@endif
                                    @endforelse
                                </td>
                                <td><bdi>{{ $refundItem->orderItem->sku }}</bdi></td>
                                <td class="number"><bdi>{{ rtrim(rtrim(number_format((float) $refundItem->quantity, 4, '.', ''), '0'), '.') }}</bdi></td>
                                <td class="number"><bdi>{{ format_store_price($refundItem->orderItem->unit_price, $refund->currency_code) }}</bdi></td>
                                <td class="number"><bdi>{{ format_store_price($refundItem->line_amount, $refund->currency_code) }}</bdi></td>
                            </tr>
                        @empty
                            <tr><td colspan="5">{{ __('shop.refund_print.no_items') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="refund-print-section refund-print-totals" aria-labelledby="refund-totals-heading">
            <h2 id="refund-totals-heading">{{ __('shop.refund_print.refund_totals') }}</h2>
            <dl>
                <div><dt>{{ __('shop.refund_print.merchandise_refund') }}</dt><dd><bdi>{{ format_store_price($refund->merchandise_amount, $refund->currency_code) }}</bdi></dd></div>
                @if ((float) $refund->shipping_deduction > 0)
                    <div><dt>{{ __('shop.refund_print.shipping_deduction') }}</dt><dd><bdi>{{ format_store_price($refund->shipping_deduction, $refund->currency_code) }}</bdi></dd></div>
                @endif
                <div class="grand-total"><dt>{{ __('shop.refund_print.customer_refund') }}</dt><dd><bdi>{{ format_store_price($refund->customer_refund_amount, $refund->currency_code) }}</bdi></dd></div>
            </dl>
        </section>
    </main>
</body>
</html>
