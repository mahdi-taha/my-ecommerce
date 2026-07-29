<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile</title>
    @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">My Profile</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="{{ route('shop.account.orders.index') }}">{{ __('shop.account.orders.my_orders') }}</a>
            <a class="btn btn-outline-primary" href="{{ route('customer.account.password.edit') }}">Change Password</a>
            <form method="POST" action="{{ route('customer.logout') }}">@csrf<button class="btn btn-outline-danger">Logout</button></form>
        </div>
    </div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card shadow-sm"><div class="card-body">
        <form method="POST" action="{{ route('customer.account.update') }}">
            @csrf @method('PUT')
            <div class="row">
                @foreach (['name' => 'Display Name', 'first_name' => 'First Name', 'last_name' => 'Last Name', 'email' => 'Email', 'phone' => 'Phone'] as $field => $label)
                    <div class="col-md-6 mb-3">
                        <label for="{{ $field }}" class="form-label">{{ $label }}{{ $field !== 'phone' ? ' *' : '' }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}"
                            value="{{ old($field, $customer->{$field}) }}" class="form-control @error($field) is-invalid @enderror"
                            @required($field !== 'phone')>
                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>
            <button class="btn btn-primary">Save Profile</button>
        </form>
    </div></div>
</main>
</body>
</html>
