<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('shop.auth.register.title') }}</title>
    @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-4">{{ __('shop.auth.register.title') }}</h1>
                    <form method="POST" action="{{ route('customer.register.store') }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">{{ __('shop.auth.register.first_name') }}</label>
                                <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}"
                                    class="form-control @error('first_name') is-invalid @enderror" required autofocus>
                                @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">{{ __('shop.auth.register.last_name') }}</label>
                                <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}"
                                    class="form-control @error('last_name') is-invalid @enderror" required>
                                @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('shop.auth.register.email') }}</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">{{ __('shop.auth.register.phone') }}</label>
                            <input id="phone" name="phone" type="text" value="{{ old('phone') }}"
                                class="form-control @error('phone') is-invalid @enderror">
                            <div class="form-text">{{ __('shop.auth.register.phone_optional') }}</div>
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('shop.auth.register.password') }}</label>
                            <input id="password" name="password" type="password"
                                class="form-control @error('password') is-invalid @enderror" required>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">{{ __('shop.auth.register.password_confirmation') }}</label>
                            <input id="password_confirmation" name="password_confirmation" type="password"
                                class="form-control" required>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">{{ __('shop.auth.register.submit') }}</button>
                    </form>
                    <p class="text-center mt-3 mb-0">
                        {{ __('shop.auth.register.have_account') }}
                        <a href="{{ route('customer.login') }}">{{ __('shop.auth.register.login') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
