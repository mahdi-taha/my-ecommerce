@extends('customer.account.layout')

@section('title', __('shop.account.addresses.title'))

@section('account-content')
    <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
        <h1 class="h3 mb-0">{{ __('shop.account.addresses.title') }}</h1>
        <a class="btn btn-primary" href="{{ route('customer.addresses.create') }}">
            {{ __('shop.account.addresses.add') }}
        </a>
    </div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    @if ($addresses->isEmpty())
        <div class="card border-0 shadow-sm"><div class="card-body py-5 text-center">
            <i class="bi bi-geo-alt display-4 text-muted"></i>
            <h2 class="h5 mt-3">{{ __('shop.account.addresses.empty') }}</h2>
        </div></div>
    @else
        <div class="row g-4">
            @foreach ($addresses as $address)
                <div class="col-md-6"><article class="card border-0 shadow-sm h-100"><div class="card-body p-4">
                    <div class="d-flex justify-content-between gap-3 mb-2">
                        <h2 class="h5 mb-0">{{ $address->label ?: __('shop.account.addresses.address') }}</h2>
                        <div class="d-flex flex-wrap gap-1">
                            @if ($address->is_default_shipping)<span class="badge bg-primary">{{ __('shop.account.addresses.default_shipping') }}</span>@endif
                            @if ($address->is_default_billing)<span class="badge bg-success">{{ __('shop.account.addresses.default_billing') }}</span>@endif
                        </div>
                    </div>
                    <address>
                        <strong>{{ $address->first_name }} {{ $address->last_name }}</strong><br>
                        @if ($address->company){{ $address->company }}<br>@endif
                        {{ $address->address_line_1 }}<br>
                        @if ($address->address_line_2){{ $address->address_line_2 }}<br>@endif
                        {{ $address->city }}, {{ $address->state }}<br>
                        @if ($address->postal_code){{ $address->postal_code }}<br>@endif
                        {{ $address->country_code }}<br>{{ $address->phone }}
                    </address>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('customer.addresses.edit', $address) }}">{{ __('shop.account.addresses.edit') }}</a>
                        @unless ($address->is_default_shipping)
                            <form method="POST" action="{{ route('customer.addresses.default-shipping', $address) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-primary">{{ __('shop.account.addresses.make_default_shipping') }}</button></form>
                        @endunless
                        @unless ($address->is_default_billing)
                            <form method="POST" action="{{ route('customer.addresses.default-billing', $address) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">{{ __('shop.account.addresses.make_default_billing') }}</button></form>
                        @endunless
                        <form method="POST" action="{{ route('customer.addresses.destroy', $address) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ __('shop.account.addresses.delete') }}</button></form>
                    </div>
                </div></article></div>
            @endforeach
        </div>
    @endif
@endsection
