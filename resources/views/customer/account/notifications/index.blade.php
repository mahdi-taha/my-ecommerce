@extends('customer.account.layout')

@section('title', __('shop.notifications.title'))

@section('account-content')

    <h1 class="h3 mb-4">{{ __('shop.notifications.title') }}</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                <div class="list-group-item p-4 {{ $notification->read_at ? '' : 'bg-light' }}">
                    <div class="d-flex flex-wrap justify-content-between gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h2 class="h6 mb-0">{{ $notification->title }}</h2>
                                @if (!$notification->read_at)
                                    <span class="badge bg-danger">{{ __('shop.notifications.unread') }}</span>
                                @endif
                            </div>
                            <p class="mb-1">{{ $notification->body }}</p>
                            <small class="text-muted">{{ $notification->created_at->format('d-m-Y H:i') }}</small>
                        </div>
                        @if (!$notification->read_at)
                            <form method="POST"
                                action="{{ route('shop.account.notifications.read', ['databaseNotification' => $notification]) }}">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-sm btn-secondary" type="submit">
                                    {{ __('shop.notifications.mark_read') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-5 px-3">
                    <i class="bi bi-bell display-5 text-muted"></i>
                    <h2 class="h5 mt-3">{{ __('shop.notifications.empty') }}</h2>
                </div>
            @endforelse
        </div>
    </div>

    @if ($notifications->hasPages())
        <div class="mt-4">{{ $notifications->links('pagination::bootstrap-5') }}</div>
    @endif
@endsection
