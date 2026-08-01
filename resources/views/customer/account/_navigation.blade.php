<nav class="nav nav-pills flex-column gap-2" aria-label="{{ __('shop.account.navigation.label') }}">
    <a class="nav-link @if (request()->routeIs('customer.account.edit')) active @endif"
        href="{{ route('customer.account.edit') }}" @if (request()->routeIs('customer.account.edit')) aria-current="page" @endif>
        {{ __('shop.account.navigation.profile') }}
    </a>
    <a class="nav-link @if (request()->routeIs('customer.addresses.*')) active @endif"
        href="{{ route('customer.addresses.index') }}" @if (request()->routeIs('customer.addresses.*')) aria-current="page" @endif>
        {{ __('shop.account.navigation.address_book') }}
    </a>
    <a class="nav-link @if (request()->routeIs('shop.account.orders.*')) active @endif"
        href="{{ route('shop.account.orders.index') }}" @if (request()->routeIs('shop.account.orders.*')) aria-current="page" @endif>
        {{ __('shop.account.orders.my_orders') }}
    </a>
    <a class="nav-link @if (request()->routeIs('shop.account.notifications.*')) active @endif"
        href="{{ route('shop.account.notifications.index') }}" @if (request()->routeIs('shop.account.notifications.*')) aria-current="page" @endif>
        {{ __('shop.notifications.title') }}
        @if ($notificationCount > 0)
            <span class="badge bg-danger ms-1">{{ $notificationCount }}</span>
        @endif
    </a>
    <a class="nav-link @if (request()->routeIs('shop.wishlist.*')) active @endif"
        href="{{ route('shop.wishlist.index') }}" @if (request()->routeIs('shop.wishlist.*')) aria-current="page" @endif>
        {{ __('shop.wishlist.title') }}
        @if (($storefrontWishlistCount ?? 0) > 0)
            <span class="badge bg-danger ms-1">{{ $storefrontWishlistCount }}</span>
        @endif
    </a>
    <a class="nav-link @if (request()->routeIs('customer.account.password.*')) active @endif"
        href="{{ route('customer.account.password.edit') }}" @if (request()->routeIs('customer.account.password.*')) aria-current="page" @endif>
        {{ __('shop.account.profile.change_password') }}
    </a>
    <form method="POST" action="{{ route('customer.logout') }}">
        @csrf
        <button class="nav-link border-0 bg-transparent text-start w-100" type="submit">
            {{ __('shop.account.profile.logout') }}
        </button>
    </form>
</nav>
