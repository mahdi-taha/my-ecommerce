<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notifications</title>
    @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])
</head>
<body>
<div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6"
    data-sidebartype="full" data-sidebar-position="fixed" data-header-position="fixed">
    <x-admin-sidebar />
    <div class="body-wrapper">
        <x-admin-topbar />
        <div class="body-wrapper-inner">
            <div class="container-fluid">
                <h1 class="h3 mb-4">Notifications</h1>

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <div class="card">
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            @forelse ($notifications as $notification)
                                <div class="list-group-item p-4 {{ $notification->read_at ? '' : 'bg-light' }}">
                                    <div class="d-flex flex-wrap justify-content-between gap-3">
                                        <div>
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <h2 class="h6 mb-0">{{ $notification->title }}</h2>
                                                @if (! $notification->read_at)
                                                    <span class="badge bg-primary">Unread</span>
                                                @endif
                                            </div>
                                            <p class="mb-1">{{ $notification->body }}</p>
                                            <small class="text-muted">{{ $notification->created_at->format('Y-m-d H:i') }}</small>
                                        </div>
                                        @if (! $notification->read_at)
                                            <form method="POST" action="{{ route('admin.notifications.read', $notification) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-sm btn-outline-primary" type="submit">Mark as read</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-center p-5 text-muted">No notifications are available.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if ($notifications->hasPages())
                    <div class="mt-4">{{ $notifications->links('pagination::bootstrap-5') }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
</body>
</html>
