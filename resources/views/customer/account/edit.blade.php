<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('shop.account.profile.title') }}</title>
    @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
<main class="container py-5">
    @include('customer.account._navigation')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ __('shop.account.profile.title') }}</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="{{ route('customer.account.password.edit') }}">{{ __('shop.account.profile.change_password') }}</a>
            <form method="POST" action="{{ route('customer.logout') }}">@csrf<button class="btn btn-outline-danger">{{ __('shop.account.profile.logout') }}</button></form>
        </div>
    </div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card shadow-sm"><div class="card-body">
        <form method="POST" action="{{ route('customer.account.update') }}">
            @csrf @method('PUT')
            <div class="row">
                @foreach (['first_name', 'last_name', 'phone'] as $field)
                    <div class="col-md-6 mb-3">
                        <label for="{{ $field }}" class="form-label">{{ __('shop.account.profile.fields.'.$field) }}{{ $field !== 'phone' ? ' *' : '' }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="text"
                            value="{{ old($field, $customer->{$field}) }}" class="form-control @error($field) is-invalid @enderror"
                            @required($field !== 'phone')>
                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>
            <button class="btn btn-primary">{{ __('shop.account.profile.save') }}</button>
        </form>
    </div></div>
</main>
</body>
</html>
