@extends('customer.account.layout')

@section('title', __('shop.account.profile.change_password'))

@section('account-content')
<div class="row justify-content-center"><div class="col-lg-6">
    <div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">{{ __('shop.account.profile.change_password') }}</h1><a class="btn btn-outline-secondary" href="{{ route('customer.account.edit') }}">{{ __('shop.account.password.back') }}</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card shadow-sm"><div class="card-body"><form method="POST" action="{{ route('customer.account.password.update') }}">
        @csrf @method('PUT')
        <div class="mb-3"><label class="form-label" for="current_password">{{ __('shop.account.password.current_password') }}</label><input class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" type="password" required>@error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label class="form-label" for="password">{{ __('shop.account.password.new_password') }}</label><input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" required>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label class="form-label" for="password_confirmation">{{ __('shop.account.password.confirm_password') }}</label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required></div>
        <button class="btn btn-primary">{{ __('shop.account.password.update') }}</button>
    </form></div></div>
</div></div>
@endsection
