<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('shop.auth.password.forgot_title') }}</title>
    <meta name="robots" content="noindex,nofollow">
    @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3">{{ __('shop.auth.password.forgot_title') }}</h1>
                    <p class="text-muted">{{ __('shop.auth.password.forgot_intro') }}</p>
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
                    @endif
                    <form method="POST" action="{{ route('customer.password.email') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('shop.auth.password.email_label') }}</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror" autocomplete="email" required autofocus
                                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            @error('email')<div id="email-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button class="btn btn-primary w-100" type="submit">{{ __('shop.auth.password.send_link') }}</button>
                    </form>
                    <p class="text-center mt-3 mb-0">
                        <a href="{{ route('customer.login') }}">{{ __('shop.auth.password.back_to_login') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
