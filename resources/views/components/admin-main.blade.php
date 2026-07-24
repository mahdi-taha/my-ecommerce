<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $page }}</title>
    {{ $header }}
</head>

<body data-page="{{ $page }}">
    {{ $slot }}
</body>
@if (session('success'))
    <div class="toast-message" data-type="success" data-message="{{ session('success') }}">
    </div>
@endif

@if (session('error'))
    <div class="toast-message" data-type="error" data-message="{{ session('error') }}">
    </div>
@endif

@if (session('info'))
    <div class="toast-message" data-type="info" data-message="{{ session('info') }}">
    </div>
@endif

</html>
