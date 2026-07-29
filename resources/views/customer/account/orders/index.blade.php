<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('shop.account.orders.my_orders') }}</title>
    @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
<main class="container py-5">
    @include('customer.account._navigation')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <h1 class="h3 mb-0">{{ __('shop.account.orders.my_orders') }}</h1>
        <a class="btn btn-outline-secondary" href="{{ route('customer.account.edit') }}">
            {{ __('shop.account.orders.back_to_account') }}
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            @if ($orders->isEmpty())
                <div class="text-center py-5 px-3">
                    <i class="bi bi-box-seam display-5 text-muted"></i>
                    <h2 class="h5 mt-3">{{ __('shop.account.orders.no_orders') }}</h2>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('shop.account.orders.order_number') }}</th>
                                <th>{{ __('shop.account.orders.order_date') }}</th>
                                <th>{{ __('shop.checkout.grand_total') }}</th>
                                <th>{{ __('shop.account.orders.status') }}</th>
                                <th>{{ __('shop.checkout.confirmation.payment_status') }}</th>
                                <th>{{ __('shop.checkout.confirmation.fulfillment_status') }}</th>
                                <th class="text-end">{{ __('shop.account.orders.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td class="fw-semibold">{{ $order->order_number }}</td>
                                    <td>{{ date('Y-m-d H:i', strtotime($order->placed_at)) }}</td>
                                    <td>{{ format_store_price($order->grand_total, $order->currency_code) }}</td>
                                    <td><span class="badge bg-secondary">{{ __('shop.checkout.status.order.'.$order->status) }}</span></td>
                                    <td><span class="badge bg-secondary">{{ __('shop.checkout.status.payment.'.$order->payment_status) }}</span></td>
                                    <td><span class="badge bg-secondary">{{ __('shop.checkout.status.fulfillment.'.$order->fulfillment_status) }}</span></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('shop.account.orders.show', $order) }}">
                                            {{ __('shop.account.orders.view_order') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($orders->hasPages())
        <div class="mt-4">{{ $orders->links('pagination::bootstrap-5') }}</div>
    @endif
</main>
</body>
</html>
