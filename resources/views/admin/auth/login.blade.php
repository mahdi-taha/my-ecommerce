<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Login</title>
    @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])
</head>

<body class="bg-light">
    <main class="min-vh-100 d-flex align-items-center justify-content-center py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-7 col-lg-5 col-xl-4">
                    <div class="card border-0 shadow">
                        <div class="card-body p-4 p-lg-5">
                            <h3 class="text-center mb-2">Admin Login</h3>
                            <p class="text-muted text-center mb-4">Sign in to access the admin panel.</p>

                            <form action="{{ route('admin.login.store') }}" method="POST">
                                @csrf

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" id="email" name="email"
                                        class="form-control @error('email') border-danger @enderror"
                                        value="{{ old('email') }}" autocomplete="email" autofocus required>
                                    @error('email')
                                        <p class="text-danger mt-1 mb-0">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" id="password" name="password"
                                        class="form-control @error('password') border-danger @enderror"
                                        autocomplete="current-password" required>
                                    @error('password')
                                        <p class="text-danger mt-1 mb-0">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="form-check mb-4">
                                    <input type="hidden" name="remember" value="0">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember"
                                        value="1" @checked(old('remember'))>
                                    <label class="form-check-label" for="remember">Remember me</label>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">Login</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>
