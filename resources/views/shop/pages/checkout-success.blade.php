@extends('shop.layouts.app')

@section('title', __('shop.checkout.confirmation.title'))

@section('content')
    <div class="container-fluid py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <i class="bi bi-check-circle-fill text-success display-3"></i>
                <h1 class="h2 fw-bold mt-3">{{ __('shop.checkout.confirmation.title') }}</h1>
                <p class="text-muted">{{ __('shop.checkout.confirmation.message', ['number' => $order->order_number]) }}</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-3"><small class="text-muted d-block">{{ __('shop.checkout.confirmation.order_number') }}</small><strong>{{ $order->order_number }}</strong></div>
                                <div class="col-md-3"><small class="text-muted d-block">{{ __('shop.checkout.confirmation.placed_at') }}</small><strong>{{ $order->placed_at }}</strong></div>
                                <div class="col-md-2"><small class="text-muted d-block">{{ __('shop.checkout.confirmation.order_status') }}</small><strong>{{ __('shop.checkout.status.order.'.$order->status) }}</strong></div>
                                <div class="col-md-2"><small class="text-muted d-block">{{ __('shop.checkout.confirmation.payment_status') }}</small><strong>{{ __('shop.checkout.status.payment.'.$order->payment_status) }}</strong></div>
                                <div class="col-md-2"><small class="text-muted d-block">{{ __('shop.checkout.confirmation.fulfillment_status') }}</small><strong>{{ __('shop.checkout.status.fulfillment.'.$order->fulfillment_status) }}</strong></div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h2 class="h5 mb-3">{{ __('shop.checkout.confirmation.items') }}</h2>
                            @foreach ($order->items as $item)
                                <div class="d-flex justify-content-between gap-3 py-3 border-bottom">
                                    <div>
                                        <strong>{{ $item->name }}</strong>
                                        <small class="d-block text-muted">{{ $item->sku }} × {{ (float) $item->quantity }}</small>
                                        @foreach ($item->options as $option)
                                            <small class="d-block text-muted">{{ $option->attribute_name }}: {{ $option->option_label }}</small>
                                        @endforeach
                                    </div>
                                    <strong>{{ format_store_price($item->row_total, $order->currency_code) }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="row g-4">
                        @foreach ([['address' => $order->billingAddress, 'label' => 'billing_address'], ['address' => $order->shippingAddress, 'label' => 'shipping_address']] as $addressBlock)
                            @php
                                $address = $addressBlock['address'];
                                $label = $addressBlock['label'];
                            @endphp
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
                                    <h2 class="h5">{{ __('shop.checkout.'.$label) }}</h2>
                                    <address class="mb-0">
                                        {{ $address->first_name }} {{ $address->last_name }}<br>
                                        {{ $address->address_line_1 }}<br>
                                        @if ($address->address_line_2){{ $address->address_line_2 }}<br>@endif
                                        {{ $address->city }}@if ($address->state), {{ $address->state }}@endif<br>
                                        {{ $address->country_code }}
                                    </address>
                                </div></div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="col-lg-4">
                    @include('shop.orders.partials.manual-payment-instructions')
                    <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
                        <h2 class="h5">{{ __('shop.checkout.shipping_method') }}</h2>
                        <p class="mb-0">{{ $order->shipping->shipping_method_name }}</p>
                    </div></div>
                    <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
                        <h2 class="h5">{{ __('shop.checkout.payment_method') }}</h2>
                        <p class="mb-0">{{ $order->payment->method_name }}</p>
                    </div></div>
                    <div class="card border-0 shadow-sm"><div class="card-body p-4">
                        <dl class="row g-2 mb-0">
                            <dt class="col-7">{{ __('shop.checkout.subtotal') }}</dt><dd class="col-5 text-end">{{ format_store_price($order->subtotal, $order->currency_code) }}</dd>
                            <dt class="col-7">{{ __('shop.checkout.tax') }}</dt><dd class="col-5 text-end">{{ format_store_price($order->tax_total, $order->currency_code) }}</dd>
                            <dt class="col-7">{{ __('shop.checkout.shipping') }}</dt><dd class="col-5 text-end">{{ format_store_price($order->shipping_total, $order->currency_code) }}</dd>
                            <dt class="col-7 border-top pt-2">{{ __('shop.checkout.grand_total') }}</dt><dd class="col-5 border-top pt-2 text-end fw-bold">{{ format_store_price($order->grand_total, $order->currency_code) }}</dd>
                        </dl>
                    </div></div>
                </div>
            </div>
        </div>
    </div>
@endsection
