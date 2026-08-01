<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('shop.auth.password.reset_title') }}</title>
    @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-4">{{ __('shop.auth.password.reset_title') }}</h1>
                    <form method="POST" action="{{ route('customer.password.store') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('shop.auth.password.email_label') }}</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $email) }}"
                                class="form-control @error('email') is-invalid @enderror" required autofocus>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('shop.auth.password.new_password') }}</label>
                            <input id="password" name="password" type="password"
                                class="form-control @error('password') is-invalid @enderror" required>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">{{ __('shop.auth.password.confirm_password') }}</label>
                            <input id="password_confirmation" name="password_confirmation" type="password"
                                class="form-control" required>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">{{ __('shop.auth.password.reset_submit') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
