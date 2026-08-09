@extends('customer.account.layout')

@section('title', __('shop.account.addresses.edit'))

@section('account-content')
    <div class="card shadow-sm"><div class="card-body p-4">
        <h1 class="h3 mb-4">{{ __('shop.account.addresses.edit') }}</h1>
        <form method="POST" action="{{ route('customer.addresses.update', ['customerAddress' => $address]) }}">
            @include('customer.account.addresses._form')
        </form>
    </div></div>
@endsection
