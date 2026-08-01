<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('shop.auth.login.title') }}</title>
    @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-4">{{ __('shop.auth.login.title') }}</h1>
                    @if (session('error'))
                        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
                    @endif
                    <form method="POST" action="{{ route('customer.login.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('shop.auth.login.email') }}</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror" autocomplete="email" required autofocus
                                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            @error('email')<div id="email-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('shop.auth.login.password') }}</label>
                            <input id="password" name="password" type="password" class="form-control"
                                autocomplete="current-password" required>
                            <div class="text-end mt-1">
                                <a href="{{ route('customer.password.request') }}">{{ __('shop.auth.password.forgot_link') }}</a>
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                            <label class="form-check-label" for="remember">{{ __('shop.auth.login.remember') }}</label>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">{{ __('shop.auth.login.submit') }}</button>
                    </form>
                    <p class="text-center mt-3 mb-0">
                        {{ __('shop.auth.register.no_account') }}
                        <a href="{{ route('customer.register') }}">{{ __('shop.auth.register.create_account') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
