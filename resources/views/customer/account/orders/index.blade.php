@extends('customer.account.layout')

@section('title', __('shop.account.orders.my_orders'))

@section('account-content')
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
                                <th scope="col">{{ __('shop.account.orders.order_number') }}</th>
                                <th scope="col">{{ __('shop.account.orders.order_date') }}</th>
                                <th scope="col">{{ __('shop.checkout.grand_total') }}</th>
                                <th scope="col">{{ __('shop.account.orders.status') }}</th>
                                <th scope="col">{{ __('shop.checkout.confirmation.payment_status') }}</th>
                                <th scope="col">{{ __('shop.checkout.confirmation.fulfillment_status') }}</th>
                                <th scope="col" class="text-end">{{ __('shop.account.orders.actions') }}</th>
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
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('shop.account.orders.show', ['order' => $order]) }}">
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
@endsection
