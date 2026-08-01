<nav class="nav nav-pills flex-wrap gap-2 mb-4" aria-label="{{ __('shop.account.navigation.label') }}">
    <a class="nav-link @if (request()->routeIs('customer.account.*')) active @endif"
        href="{{ route('customer.account.edit') }}">
        {{ __('shop.account.navigation.profile') }}
    </a>
    <a class="nav-link @if (request()->routeIs('customer.addresses.*')) active @endif"
        href="{{ route('customer.addresses.index') }}">
        {{ __('shop.account.navigation.address_book') }}
    </a>
    <a class="nav-link @if (request()->routeIs('shop.account.orders.*')) active @endif"
        href="{{ route('shop.account.orders.index') }}">
        {{ __('shop.account.orders.my_orders') }}
    </a>
    <a class="nav-link @if (request()->routeIs('shop.account.notifications.*')) active @endif"
        href="{{ route('shop.account.notifications.index') }}">
        {{ __('shop.notifications.title') }}
        @if ($notificationCount > 0)
            <span class="badge bg-danger ms-1">{{ $notificationCount }}</span>
        @endif
    </a>
    <a class="nav-link @if (request()->routeIs('shop.wishlist.*')) active @endif"
        href="{{ route('shop.wishlist.index') }}">
        {{ __('shop.wishlist.title') }}
    </a>
</nav>
