@extends('shop.layouts.app')

@section('title', __('shop.checkout.title'))

@section('content')
    <div class="container-fluid py-5">
        <div class="container py-4">
            <h1 class="h2 fw-bold mb-4">{{ __('shop.checkout.title') }}</h1>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! $summary->isValid())
                <div class="alert alert-warning" role="alert">
                    {{ $summary->errors[0]['message'] ?? __('shop.checkout.failures.order_placement_failed') }}
                </div>
            @endif

            <form action="{{ route('shop.checkout.store') }}" method="POST"
                data-checkout-form
                data-summary-url="{{ route('shop.checkout.summary') }}"
                data-summary-loading="{{ __('shop.checkout.summary_updating') }}"
                data-summary-error="{{ __('shop.checkout.summary_update_failed') }}">
                @csrf
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <h2 class="h5 mb-3">{{ __('shop.checkout.customer_information') }}</h2>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="customer_first_name">{{ __('shop.checkout.fields.first_name') }}</label>
                                        <input id="customer_first_name" name="customer[first_name]" class="form-control @error('customer.first_name') is-invalid @enderror"
                                            value="{{ old('customer.first_name', $customer?->first_name) }}" required>
                                        @error('customer.first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="customer_last_name">{{ __('shop.checkout.fields.last_name') }}</label>
                                        <input id="customer_last_name" name="customer[last_name]" class="form-control @error('customer.last_name') is-invalid @enderror"
                                            value="{{ old('customer.last_name', $customer?->last_name) }}" required>
                                        @error('customer.last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="customer_phone">{{ __('shop.checkout.fields.phone') }}</label>
                                        <input id="customer_phone" name="customer[phone]" class="form-control @error('customer.phone') is-invalid @enderror"
                                            value="{{ old('customer.phone', $customer?->phone) }}" required>
                                        @error('customer.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="customer_email">{{ __('shop.checkout.fields.email_optional') }}</label>
                                        <input type="email" id="customer_email" name="customer[email]" class="form-control @error('customer.email') is-invalid @enderror"
                                            value="{{ old('customer.email', $customer?->email) }}">
                                        @error('customer.email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        @foreach (['billing_address' => 'billing_address', 'shipping_address' => 'shipping_address'] as $prefix => $heading)
                            <div class="card border-0 shadow-sm mb-4" data-address-section="{{ $prefix }}">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h2 class="h5 mb-0">{{ __('shop.checkout.'.$heading) }}</h2>
                                        @if ($prefix === 'shipping_address')
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="same_as_billing">
                                                <label class="form-check-label" for="same_as_billing">{{ __('shop.checkout.same_as_billing') }}</label>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="row g-3">
                                        @foreach (['first_name', 'last_name', 'company', 'email', 'phone', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country_code'] as $field)
                                            @php
                                                $required = in_array($field, ['first_name', 'last_name', 'address_line_1', 'city', 'country_code'], true);
                                                $column = in_array($field, ['address_line_1', 'address_line_2'], true) ? 'col-12' : 'col-md-6';
                                            @endphp
                                            <div class="{{ $column }}">
                                                <label class="form-label" for="{{ $prefix }}_{{ $field }}">{{ __('shop.checkout.fields.'.$field) }}</label>
                                                <input
                                                    type="{{ $field === 'email' ? 'email' : 'text' }}"
                                                    id="{{ $prefix }}_{{ $field }}"
                                                    name="{{ $prefix }}[{{ $field }}]"
                                                    value="{{ old($prefix.'.'.$field, in_array($field, ['first_name', 'last_name', 'email', 'phone'], true) ? $customer?->{$field} : null) }}"
                                                    class="form-control @error($prefix.'.'.$field) is-invalid @enderror"
                                                    @if ($required) required @endif
                                                    @if ($field === 'country_code') maxlength="2" @endif>
                                                @error($prefix.'.'.$field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <h2 class="h5 mb-3">{{ __('shop.checkout.shipping_method') }}</h2>
                                @forelse ($shippingMethods as $method)
                                    <div class="form-check border rounded p-3 ps-5 mb-2">
                                        <input class="form-check-input" type="radio" name="shipping_method"
                                            id="shipping_{{ $method->id }}" value="{{ $method->code }}"
                                            @checked(old('shipping_method', $shippingCode) === $method->code) required>
                                        <label class="form-check-label d-flex justify-content-between w-100" for="shipping_{{ $method->id }}">
                                            <span>{{ $method->name }}</span>
                                            <strong>{{ format_store_price($method->amount, $summary->currencyCode) }}</strong>
                                        </label>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">{{ __('shop.checkout.no_shipping_methods') }}</p>
                                @endforelse
                                @error('shipping_method')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h2 class="h5 mb-3">{{ __('shop.checkout.payment_method') }}</h2>
                                @forelse ($paymentMethods as $method)
                                    <div class="form-check border rounded p-3 ps-5 mb-2">
                                        <input class="form-check-input" type="radio" name="payment_method"
                                            id="payment_{{ $method->id }}" value="{{ $method->code }}"
                                            @checked(old('payment_method', $paymentCode) === $method->code) required>
                                        <label class="form-check-label" for="payment_{{ $method->id }}">{{ $method->name }}</label>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">{{ __('shop.checkout.no_payment_methods') }}</p>
                                @endforelse
                                @error('payment_method')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card border-0 shadow-sm position-sticky" style="top: 1rem;">
                            <div class="card-body p-4">
                                <h2 class="h5 mb-3">{{ __('shop.checkout.order_summary') }}</h2>
                                @foreach ($summary->items as $item)
                                    <div class="border-bottom py-3">
                                        <div class="d-flex justify-content-between gap-3">
                                            <div>
                                                <span class="fw-semibold">{{ $item['name'] }}</span>
                                                <small class="d-block text-muted">{{ $item['sku'] }} × {{ (float) $item['quantity'] }}</small>
                                                @foreach ($item['options'] as $option)
                                                    <small class="d-block text-muted">{{ $option['attribute_name'] }}: {{ $option['option_label'] }}</small>
                                                @endforeach
                                            </div>
                                            <strong>{{ format_store_price($item['row_total'], $summary->currencyCode) }}</strong>
                                        </div>
                                    </div>
                                @endforeach
                                <dl class="row g-2 mt-3 mb-0">
                                    <dt class="col-7">{{ __('shop.checkout.subtotal') }}</dt>
                                    <dd class="col-5 text-end" data-checkout-subtotal>{{ format_store_price($summary->subtotal, $summary->currencyCode) }}</dd>
                                    <dt class="col-7">{{ __('shop.checkout.tax') }}</dt>
                                    <dd class="col-5 text-end" data-checkout-tax-total>{{ format_store_price($summary->taxTotal, $summary->currencyCode) }}</dd>
                                    <dt class="col-7">{{ __('shop.checkout.shipping') }}</dt>
                                    <dd class="col-5 text-end" data-checkout-shipping-amount>{{ format_store_price($summary->shippingAmount, $summary->currencyCode) }}</dd>
                                    <dt class="col-7 fs-5 pt-2 border-top">{{ __('shop.checkout.grand_total') }}</dt>
                                    <dd class="col-5 text-end fs-5 fw-bold text-primary pt-2 border-top" data-checkout-grand-total>{{ format_store_price($summary->grandTotal, $summary->currencyCode) }}</dd>
                                </dl>
                                <p class="small text-muted mt-3 mb-0" role="status" aria-live="polite"
                                    data-checkout-summary-status></p>
                                <button type="submit" class="btn btn-primary w-100 text-uppercase mt-4"
                                    data-checkout-place-order
                                    @disabled(! $summary->isValid() || $shippingMethods->isEmpty() || $paymentMethods->isEmpty())>
                                    {{ __('shop.checkout.place_order') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('same_as_billing')?.addEventListener('change', function () {
            if (!this.checked) return;
            document.querySelectorAll('[name^="billing_address["]').forEach(function (source) {
                const target = document.querySelector('[name="shipping_address[' + source.name.slice(16) + '"]');
                if (target) target.value = source.value;
            });
        });
    </script>
@endpush
