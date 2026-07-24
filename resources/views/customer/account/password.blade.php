<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change Password</title>
    @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/js/app.js'])
</head>
<body class="bg-light"><main class="container py-5"><div class="row justify-content-center"><div class="col-lg-6">
    <div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Change Password</h1><a class="btn btn-outline-secondary" href="{{ route('customer.account.edit') }}">Back</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card shadow-sm"><div class="card-body"><form method="POST" action="{{ route('customer.account.password.update') }}">
        @csrf @method('PUT')
        <div class="mb-3"><label class="form-label" for="current_password">Current Password</label><input class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" type="password" required>@error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label class="form-label" for="password">New Password</label><input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" required>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label class="form-label" for="password_confirmation">Confirm Password</label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required></div>
        <button class="btn btn-primary">Update Password</button>
    </form></div></div>
</div></div></main></body></html>
