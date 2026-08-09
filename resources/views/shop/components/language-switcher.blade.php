<div class="dropdown">
    <button class="btn btn-link dropdown-toggle text-muted mx-2 p-0 text-decoration-none" type="button"
        data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('shop.topbar.language') }}">
        <small>{{ strtoupper(app()->getLocale()) }}</small>
    </button>
    <div class="dropdown-menu rounded">
        @foreach (['en' => __('shop.topbar.english'), 'ar' => __('shop.topbar.arabic')] as $locale => $label)
            <form method="POST" action="{{ route('shop.locale.update', ['locale' => app()->getLocale(), 'targetLocale' => $locale]) }}">
                @csrf
                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                <button type="submit" class="dropdown-item"
                    @if (app()->getLocale() === $locale) aria-current="true" @endif>
                    {{ $label }}
                </button>
            </form>
        @endforeach
    </div>
</div>
