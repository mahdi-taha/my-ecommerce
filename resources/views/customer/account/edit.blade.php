@extends('customer.account.layout')

@section('title', __('shop.account.profile.title'))

@section('account-content')
    <h1 class="h3 mb-3">{{ __('shop.account.profile.title') }}</h1>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('customer.account.update') }}">
                @csrf @method('PUT')
                <div class="row">
                    @foreach (['first_name', 'last_name', 'phone'] as $field)
                        <div class="col-md-6 mb-3">
                            <label for="{{ $field }}"
                                class="form-label text-dark"><b>{{ __('shop.account.profile.fields.' . $field) }}{{ $field !== 'phone' ? ' *' : '' }}</b></label>
                            <input id="{{ $field }}" name="{{ $field }}" type="text"
                                value="{{ old($field, $customer->{$field}) }}"
                                class="form-control text-dark @error($field) is-invalid @enderror" @required($field !== 'phone')>
                            @error($field)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach
                </div>
                <button class="btn btn-primary">{{ __('shop.account.profile.save') }}</button>
            </form>
        </div>
    </div>
@endsection
