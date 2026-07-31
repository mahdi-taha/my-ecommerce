<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('shop.notifications.title') }}</title>
    @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
<main class="container py-5">
    @include('customer.account._navigation')

    <h1 class="h3 mb-4">{{ __('shop.notifications.title') }}</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                <div class="list-group-item p-4 {{ $notification->read_at ? '' : 'bg-light' }}">
                    <div class="d-flex flex-wrap justify-content-between gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h2 class="h6 mb-0">{{ $notification->title }}</h2>
                                @if (! $notification->read_at)
                                    <span class="badge bg-primary">{{ __('shop.notifications.unread') }}</span>
                                @endif
                            </div>
                            <p class="mb-1">{{ $notification->body }}</p>
                            <small class="text-muted">{{ $notification->created_at->format('Y-m-d H:i') }}</small>
                        </div>
                        @if (! $notification->read_at)
                            <form method="POST" action="{{ route('shop.account.notifications.read', $notification) }}">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-sm btn-outline-primary" type="submit">
                                    {{ __('shop.notifications.mark_read') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center p-5 text-muted">{{ __('shop.notifications.empty') }}</div>
            @endforelse
        </div>
    </div>

    @if ($notifications->hasPages())
        <div class="mt-4">{{ $notifications->links('pagination::bootstrap-5') }}</div>
    @endif
</main>
</body>
</html>
