<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', config('app.name'))</title>

    @yield('meta')

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    <!-- Icon Fonts -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries -->
    <link href="{{ asset('shop/lib/animate/animate.min.css') }}" rel="stylesheet">
    <link href="{{ asset('shop/lib/owlcarousel/assets/owl.carousel.min.css') }}" rel="stylesheet">

    <!-- Bootstrap -->
    <link href="{{ asset('shop/css/bootstrap.min.css') }}" rel="stylesheet">

    <!-- Template -->
    <link href="{{ asset('shop/css/style.css') }}" rel="stylesheet">

    {{-- CSS --}}
    @vite(['resources/js/shop.js', 'resources/css/shop.css'])

    @stack('styles')
</head>

<body>

    @include('shop.components.topbar')

    @include('shop.components.navbar')
    

    <main>
        @yield('content')
    </main>

    @include('shop.components.footer')

    <div class="visually-hidden" role="status" aria-live="polite" aria-atomic="true"
        data-storefront-action-status
        data-request-failed="{{ __('shop.storefront_actions.request_failed') }}"></div>

    @if (session('success'))
        <div class="toast-message" data-type="success" data-message="{{ session('success') }}"></div>
    @endif
    @if (session('warning'))
        <div class="toast-message" data-type="warning" data-message="{{ session('warning') }}"></div>
    @endif
    @if (session('error'))
        <div class="toast-message" data-type="error" data-message="{{ session('error') }}"></div>
    @endif

    {{-- JS --}}
    @stack('scripts')

</body>

</html>
