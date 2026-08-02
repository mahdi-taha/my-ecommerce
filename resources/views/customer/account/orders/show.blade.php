@extends('customer.account.layout')

@section('title', __('shop.account.orders.order_details'))

@php
    $billingAddress = $order->addresses->firstWhere('type', 'billing');
    $shippingAddress = $order->addresses->firstWhere('type', 'shipping');
@endphp
@section('account-content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ __('shop.account.orders.order_details') }}</h1>
            <span class="text-muted">{{ $order->order_number }}</span>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('shop.account.orders.index') }}">
            {{ __('shop.account.orders.back_to_orders') }}
        </a>
    </div>

    <div class="card shadow-sm border-0 mb-4"><div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-4"><small class="text-muted d-block">{{ __('shop.account.orders.order_number') }}</small><strong>{{ $order->order_number }}</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">{{ __('shop.account.orders.order_date') }}</small><strong>{{ date('Y-m-d H:i', strtotime($order->placed_at)) }}</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">{{ __('shop.account.orders.customer') }}</small><strong>{{ $order->customer_first_name }} {{ $order->customer_last_name }}</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">{{ __('shop.account.orders.status') }}</small><strong>{{ __('shop.checkout.status.order.'.$order->status) }}</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">{{ __('shop.checkout.confirmation.payment_status') }}</small><strong>{{ __('shop.checkout.status.payment.'.$order->payment_status) }}</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">{{ __('shop.checkout.confirmation.fulfillment_status') }}</small><strong>{{ __('shop.checkout.status.fulfillment.'.$order->fulfillment_status) }}</strong></div>
        </div>
    </div></div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">{{ __('shop.account.orders.cancellation.title') }}</h2>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @foreach ($order->cancellationRequests as $cancellationRequest)
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                        <strong>{{ __('shop.account.orders.cancellation.status_'.$cancellationRequest->status->value) }}</strong>
                        <small class="text-muted">{{ $cancellationRequest->created_at?->format('Y-m-d H:i') }}</small>
                    </div>
                    <p class="mb-1"><strong>{{ __('shop.account.orders.cancellation.reason') }}:</strong> {{ $cancellationRequest->reason }}</p>
                    @if (in_array($cancellationRequest->status, [
                        \App\Enums\OrderCancellationRequestStatus::Approved,
                        \App\Enums\OrderCancellationRequestStatus::Rejected,
                    ], true) && trim((string) $cancellationRequest->admin_note) !== '')
                        <p class="mb-0"><strong>{{ __('shop.account.orders.cancellation.admin_note') }}:</strong> {{ $cancellationRequest->admin_note }}</p>
                    @endif
                </div>
            @endforeach

            @if ($canRequestCancellation)
                <form method="POST" action="{{ route('shop.account.orders.cancellation-requests.store', ['order' => $order]) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="cancellation-reason">{{ __('shop.account.orders.cancellation.reason') }}</label>
                        <textarea id="cancellation-reason" name="reason" rows="4"
                            class="form-control @error('reason') is-invalid @enderror" required maxlength="2000">{{ old('reason') }}</textarea>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-outline-danger" type="submit">
                        {{ __('shop.account.orders.cancellation.request_button') }}
                    </button>
                </form>
            @elseif ($order->cancellationRequests->isEmpty())
                <p class="text-muted mb-0">{{ __('shop.account.orders.cancellation.not_eligible') }}</p>
            @endif
        </div>
    </div>

    <div class="row g-4 mb-4">
        @foreach ([['address' => $billingAddress, 'label' => 'billing_address'], ['address' => $shippingAddress, 'label' => 'shipping_address']] as $block)
            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100"><div class="card-body p-4">
                    <h2 class="h5">{{ __('shop.checkout.'.$block['label']) }}</h2>
                    @if ($block['address'])
                        <address class="mb-0">
                            {{ $block['address']->first_name }} {{ $block['address']->last_name }}<br>
                            @if ($block['address']->company){{ $block['address']->company }}<br>@endif
                            {{ $block['address']->address_line_1 }}<br>
                            @if ($block['address']->address_line_2){{ $block['address']->address_line_2 }}<br>@endif
                            {{ $block['address']->city }}@if ($block['address']->state), {{ $block['address']->state }}@endif<br>
                            {{ $block['address']->country_code }}
                        </address>
                    @endif
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6"><div class="card shadow-sm border-0 h-100"><div class="card-body p-4">
            <h2 class="h5">{{ __('shop.checkout.shipping_method') }}</h2>
            <p class="mb-1">{{ $order->shipping?->shipping_method_name }}</p>
            <strong>{{ format_store_price($order->shipping?->shipping_amount ?? $order->shipping_total, $order->currency_code) }}</strong>
        </div></div></div>
        <div class="col-md-6"><div class="card shadow-sm border-0 h-100"><div class="card-body p-4">
            <h2 class="h5">{{ __('shop.checkout.payment_method') }}</h2>
            <p class="mb-1">{{ $order->payment?->method_name }}</p>
            <strong>{{ __('shop.checkout.status.payment.'.$order->payment_status) }}</strong>
        </div></div></div>
    </div>

    @include('shop.orders.partials.manual-payment-instructions')

    <div class="card shadow-sm border-0 mb-4"><div class="card-body p-4">
        <h2 class="h5 mb-3">{{ __('shop.checkout.confirmation.items') }}</h2>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr>
                <th scope="col">{{ __('shop.account.orders.product') }}</th>
                <th scope="col">{{ __('shop.account.orders.sku') }}</th>
                <th scope="col" class="text-end">{{ __('shop.product_details.quantity') }}</th>
                <th scope="col" class="text-end">{{ __('shop.cart.unit_price') }}</th>
                <th scope="col" class="text-end">{{ __('shop.cart.line_total') }}</th>
            </tr></thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->name }}</strong>
                            @foreach ($item->options as $option)
                                <small class="d-block text-muted">{{ $option->attribute_name }}: {{ $option->option_label }}</small>
                            @endforeach
                        </td>
                        <td>{{ $item->sku }}</td>
                        <td class="text-end">{{ (float) $item->quantity }}</td>
                        <td class="text-end">{{ format_store_price($item->unit_price, $order->currency_code) }}</td>
                        <td class="text-end">{{ format_store_price($item->row_total, $order->currency_code) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </div></div>

    <div class="row g-4">
        <div class="col-lg-7"><div class="card shadow-sm border-0 h-100"><div class="card-body p-4">
            <h2 class="h5 mb-3">{{ __('shop.account.orders.timeline') }}</h2>
            @forelse ($order->statusHistory as $history)
                <div class="border-start border-primary ps-3 pb-3">
                    <strong>{{ __('shop.checkout.status.'.$history->type.'.'.$history->to_status) }}</strong>
                    <small class="d-block text-muted">{{ $history->created_at?->format('Y-m-d H:i') }}</small>
                    @if ($history->comment)<p class="mb-0 mt-1">{{ $history->comment }}</p>@endif
                </div>
            @empty
                <p class="text-muted mb-0">{{ __('shop.account.orders.no_history') }}</p>
            @endforelse
        </div></div></div>
        <div class="col-lg-5"><div class="card shadow-sm border-0"><div class="card-body p-4">
            <h2 class="h5 mb-3">{{ __('shop.account.orders.totals') }}</h2>
            <dl class="row g-2 mb-0">
                <dt class="col-7">{{ __('shop.checkout.subtotal') }}</dt><dd class="col-5 text-end">{{ format_store_price($order->subtotal, $order->currency_code) }}</dd>
                <dt class="col-7">{{ __('shop.checkout.tax') }}</dt><dd class="col-5 text-end">{{ format_store_price($order->tax_total, $order->currency_code) }}</dd>
                <dt class="col-7">{{ __('shop.checkout.shipping') }}</dt><dd class="col-5 text-end">{{ format_store_price($order->shipping_total, $order->currency_code) }}</dd>
                <dt class="col-7 border-top pt-2">{{ __('shop.checkout.grand_total') }}</dt><dd class="col-5 border-top pt-2 text-end fw-bold">{{ format_store_price($order->grand_total, $order->currency_code) }}</dd>
            </dl>
        </div></div></div>
    </div>
@endsection
