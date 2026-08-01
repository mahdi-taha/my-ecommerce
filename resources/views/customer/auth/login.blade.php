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
                    <form method="POST" action="{{ route('customer.login.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('shop.auth.login.email') }}</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror" required autofocus>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('shop.auth.login.password') }}</label>
                            <input id="password" name="password" type="password" class="form-control" required>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                            <label class="form-check-label" for="remember">{{ __('shop.auth.login.remember') }}</label>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">{{ __('shop.auth.login.submit') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
