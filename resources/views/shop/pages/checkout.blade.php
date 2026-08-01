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

            @foreach ($summary->warnings as $warning)
                <div class="alert alert-warning" role="alert">{{ $warning }}</div>
            @endforeach

            <form action="{{ route('shop.checkout.store') }}" method="POST"
                data-checkout-form
                data-summary-url="{{ route('shop.checkout.summary') }}"
                data-coupon-apply-url="{{ route('shop.checkout.coupon.store') }}"
                data-coupon-remove-url="{{ route('shop.checkout.coupon.destroy') }}"
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
                                            value="{{ old('customer.first_name', $customer?->first_name) }}" autocomplete="given-name" required
                                            @error('customer.first_name') aria-invalid="true" aria-describedby="customer-first-name-error" @enderror>
                                        @error('customer.first_name')<div id="customer-first-name-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="customer_last_name">{{ __('shop.checkout.fields.last_name') }}</label>
                                        <input id="customer_last_name" name="customer[last_name]" class="form-control @error('customer.last_name') is-invalid @enderror"
                                            value="{{ old('customer.last_name', $customer?->last_name) }}" autocomplete="family-name" required
                                            @error('customer.last_name') aria-invalid="true" aria-describedby="customer-last-name-error" @enderror>
                                        @error('customer.last_name')<div id="customer-last-name-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="customer_phone">{{ __('shop.checkout.fields.phone') }}</label>
                                        <input id="customer_phone" name="customer[phone]" class="form-control @error('customer.phone') is-invalid @enderror"
                                            value="{{ old('customer.phone', $customer?->phone) }}" autocomplete="tel" required
                                            @error('customer.phone') aria-invalid="true" aria-describedby="customer-phone-error" @enderror>
                                        @error('customer.phone')<div id="customer-phone-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="customer_email">{{ __('shop.checkout.fields.email_optional') }}</label>
                                        <input type="email" id="customer_email" name="customer[email]" class="form-control @error('customer.email') is-invalid @enderror"
                                            value="{{ old('customer.email', $customer?->email) }}" autocomplete="email"
                                            @error('customer.email') aria-invalid="true" aria-describedby="customer-email-error" @enderror>
                                        @error('customer.email')<div id="customer-email-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        @php
                            $initialAddressSource = old('address_source', $customer && $defaultShippingAddress ? 'saved' : 'manual');
                            $initialSavedAddress = old('saved_address_id', $defaultShippingAddress?->getKey());
                        @endphp

                        <div class="card border-0 shadow-sm mb-4" data-checkout-addresses>
                            <div class="card-body p-4">
                                <h2 class="h5 mb-3">{{ __('shop.checkout.addresses.title') }}</h2>

                                @if ($customer)
                                    <h3 class="h6">{{ __('shop.checkout.addresses.saved') }}</h3>
                                    @forelse ($savedAddresses as $address)
                                        <div class="border rounded p-3 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="saved_address_id"
                                                    id="saved_address_{{ $address->id }}" value="{{ $address->id }}"
                                                    @checked((string) $initialSavedAddress === (string) $address->id)>
                                                <label class="form-check-label w-100" for="saved_address_{{ $address->id }}">
                                                    <span class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                                        <strong>{{ $address->label ?: __('shop.checkout.addresses.saved') }}</strong>
                                                        @if ($address->is_default_shipping)
                                                            <span class="badge bg-primary">{{ __('shop.checkout.addresses.default_shipping') }}</span>
                                                        @endif
                                                        @if ($address->is_default_billing)
                                                            <span class="badge bg-secondary">{{ __('shop.checkout.addresses.default_billing') }}</span>
                                                        @endif
                                                    </span>
                                                    <span class="d-block">{{ $address->first_name }} {{ $address->last_name }} · {{ $address->phone }}</span>
                                                    <small class="text-muted">
                                                        {{ collect([$address->address_line_1, $address->address_line_2, $address->city, $address->state, $address->country_code])->filter()->implode(', ') }}
                                                    </small>
                                                </label>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-muted">{{ __('shop.checkout.addresses.none_saved') }}</p>
                                    @endforelse

                                    @if ($savedAddresses->isNotEmpty())
                                        <div class="form-check mt-3">
                                            <input class="form-check-input" type="radio" name="address_source"
                                                id="address_source_saved" value="saved" @checked($initialAddressSource === 'saved')>
                                            <label class="form-check-label" for="address_source_saved">
                                                {{ __('shop.checkout.addresses.use_selected') }}
                                            </label>
                                        </div>
                                    @endif

                                    <div class="d-flex align-items-center gap-3 my-4" aria-hidden="true">
                                        <hr class="flex-grow-1 my-0">
                                        <span class="text-muted small">{{ __('shop.checkout.addresses.or_manual') }}</span>
                                        <hr class="flex-grow-1 my-0">
                                    </div>
                                @endif

                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="address_source"
                                        id="address_source_manual" value="manual" @checked($initialAddressSource === 'manual') required>
                                    <label class="form-check-label fw-semibold" for="address_source_manual">
                                        {{ __('shop.checkout.addresses.new') }}
                                    </label>
                                </div>

                                <div class="row g-3" data-manual-address>
                                    @foreach (['label', 'first_name', 'last_name', 'company', 'email', 'phone', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country_code'] as $field)
                                        @php
                                            $required = in_array($field, ['first_name', 'last_name', 'address_line_1', 'city', 'country_code'], true);
                                            $column = in_array($field, ['address_line_1', 'address_line_2'], true) ? 'col-12' : 'col-md-6';
                                            $autocomplete = match ($field) {
                                                'first_name' => 'given-name',
                                                'last_name' => 'family-name',
                                                'company' => 'organization',
                                                'email' => 'email',
                                                'phone' => 'tel',
                                                'address_line_1' => 'address-line1',
                                                'address_line_2' => 'address-line2',
                                                'city' => 'address-level2',
                                                'state' => 'address-level1',
                                                'postal_code' => 'postal-code',
                                                'country_code' => 'country',
                                                default => 'off',
                                            };
                                        @endphp
                                        <div class="{{ $column }}">
                                            <label class="form-label" for="manual_address_{{ $field }}">{{ __('shop.checkout.fields.'.$field) }}</label>
                                            <input
                                                type="{{ $field === 'email' ? 'email' : 'text' }}"
                                                id="manual_address_{{ $field }}"
                                                name="manual_address[{{ $field }}]"
                                                value="{{ old('manual_address.'.$field) }}"
                                                class="form-control @error('manual_address.'.$field) is-invalid @enderror"
                                                autocomplete="{{ $autocomplete }}"
                                                @error('manual_address.'.$field) aria-invalid="true" aria-describedby="manual-address-{{ str_replace('_', '-', $field) }}-error" @enderror
                                                @if ($field === 'country_code') maxlength="2" @endif>
                                            @error('manual_address.'.$field)<div id="manual-address-{{ str_replace('_', '-', $field) }}-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    @endforeach
                                </div>

                                @if ($customer)
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" name="save_address" value="1"
                                            id="save_address" @checked(old('save_address'))>
                                        <label class="form-check-label" for="save_address">{{ __('shop.checkout.addresses.save') }}</label>
                                    </div>
                                    <div class="mt-2 {{ old('save_address') ? '' : 'd-none' }}" data-address-defaults>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="make_default_shipping" value="1"
                                                id="make_default_shipping" @checked(old('make_default_shipping'))>
                                            <label class="form-check-label" for="make_default_shipping">{{ __('shop.checkout.addresses.make_default_shipping') }}</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="make_default_billing" value="1"
                                                id="make_default_billing" @checked(old('make_default_billing'))>
                                            <label class="form-check-label" for="make_default_billing">{{ __('shop.checkout.addresses.make_default_billing') }}</label>
                                        </div>
                                    </div>
                                @endif
                                @error('address_source')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                                @error('saved_address_id')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                                @error('manual_address')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            </div>
                        </div>

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

                                <div class="border-bottom py-3">
                                    <label for="checkout_coupon_code" class="form-label fw-semibold">
                                        {{ __('shop.checkout.coupon.label') }}
                                    </label>
                                    <div class="input-group" data-checkout-coupon-entry @if($summary->coupon) hidden @endif>
                                        <input type="text" id="checkout_coupon_code" name="coupon_code"
                                            class="form-control" maxlength="100"
                                            placeholder="{{ __('shop.checkout.coupon.placeholder') }}"
                                            value="{{ old('coupon_code') }}"
                                            data-checkout-coupon-code>
                                        <button type="submit" class="btn btn-outline-primary"
                                            formaction="{{ route('shop.checkout.coupon.store') }}"
                                            data-checkout-coupon-apply>
                                            {{ __('shop.checkout.coupon.apply') }}
                                        </button>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center gap-2"
                                        data-checkout-coupon-applied @if(! $summary->coupon) hidden @endif>
                                        <span>
                                            {{ __('shop.checkout.coupon.applied_code') }}:
                                            <strong data-checkout-coupon-name>{{ $summary->coupon['code'] ?? '' }}</strong>
                                        </span>
                                        <button type="submit" form="checkout-coupon-remove-form"
                                            class="btn btn-sm btn-outline-danger"
                                            data-checkout-coupon-remove>
                                            {{ __('shop.checkout.coupon.remove') }}
                                        </button>
                                    </div>
                                    <p class="small mt-2 mb-0" role="status" aria-live="polite"
                                        data-checkout-coupon-status></p>
                                </div>
                                <dl class="row g-2 mt-3 mb-0">
                                    <dt class="col-7">{{ __('shop.checkout.subtotal') }}</dt>
                                    <dd class="col-5 text-end" data-checkout-subtotal>{{ format_store_price($summary->subtotal, $summary->currencyCode) }}</dd>
                                    <dt class="col-7 text-success" data-checkout-discount-label @if((float) $summary->discountTotal <= 0) hidden @endif>{{ __('shop.checkout.discount') }}</dt>
                                    <dd class="col-5 text-end text-success" data-checkout-discount-total @if((float) $summary->discountTotal <= 0) hidden @endif>-{{ format_store_price($summary->discountTotal, $summary->currencyCode) }}</dd>
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
            <form id="checkout-coupon-remove-form" action="{{ route('shop.checkout.coupon.destroy') }}" method="POST" class="d-none">
                @csrf
                @method('DELETE')
                <input type="hidden" name="shipping_method" value="{{ $shippingCode }}">
                <input type="hidden" name="payment_method" value="{{ $paymentCode }}">
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[name="saved_address_id"]').forEach(function (address) {
            address.addEventListener('change', function () {
                const source = document.getElementById('address_source_saved');
                if (source) source.checked = true;
            });
        });
        document.getElementById('save_address')?.addEventListener('change', function () {
            document.querySelector('[data-address-defaults]')?.classList.toggle('d-none', !this.checked);
        });
    </script>
@endpush
