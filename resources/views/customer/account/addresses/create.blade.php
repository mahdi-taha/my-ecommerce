<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('shop.account.addresses.add') }}</title>
    @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/js/app.js'])
</head>
<body class="bg-light"><main class="container py-5">
    @include('customer.account._navigation')
    <div class="card border-0 shadow-sm"><div class="card-body p-4">
        <h1 class="h3 mb-4">{{ __('shop.account.addresses.add') }}</h1>
        <form method="POST" action="{{ route('customer.addresses.store') }}">
            @include('customer.account.addresses._form')
        </form>
    </div></div>
</main></body></html>
