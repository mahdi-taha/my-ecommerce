<nav class="storefront-account-navigation mb-4"
    aria-label="{{ __('shop.account.navigation.label') }}">
    <div class="nav nav-pills storefront-account-nav-carousel"
        data-customer-account-carousel
        data-item-count="8"
        data-previous-label="{{ __('shop.account.navigation.previous') }}"
        data-next-label="{{ __('shop.account.navigation.next') }}">

        <div class="storefront-account-nav-slide">
            <a class="nav-link d-flex align-items-center justify-content-center gap-2 @if (request()->routeIs('customer.account.edit')) active @endif"
                href="{{ route('customer.account.edit') }}"
                @if (request()->routeIs('customer.account.edit')) aria-current="page" @endif>
                <i class="bi bi-person" aria-hidden="true"></i>
                <span>{{ __('shop.account.navigation.profile') }}</span>
            </a>
        </div>

        <div class="storefront-account-nav-slide">
            <a class="nav-link d-flex align-items-center justify-content-center gap-2 @if (request()->routeIs('customer.addresses.*')) active @endif"
                href="{{ route('customer.addresses.index') }}"
                @if (request()->routeIs('customer.addresses.*')) aria-current="page" @endif>
                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                <span>{{ __('shop.account.navigation.address_book') }}</span>
            </a>
        </div>

        <div class="storefront-account-nav-slide">
            <a class="nav-link d-flex align-items-center justify-content-center gap-2 @if (request()->routeIs('shop.account.orders.*')) active @endif"
                href="{{ route('shop.account.orders.index') }}"
                @if (request()->routeIs('shop.account.orders.*')) aria-current="page" @endif>
                <i class="bi bi-bag-check" aria-hidden="true"></i>
                <span>{{ __('shop.account.orders.my_orders') }}</span>
            </a>
        </div>

        <div class="storefront-account-nav-slide">
            <a class="nav-link d-flex align-items-center justify-content-center gap-2 @if (request()->routeIs('shop.account.notifications.*')) active @endif"
                href="{{ route('shop.account.notifications.index') }}"
                @if (request()->routeIs('shop.account.notifications.*')) aria-current="page" @endif>
                <i class="bi bi-bell" aria-hidden="true"></i>
                <span>{{ __('shop.notifications.title') }}</span>

                @if ($notificationCount > 0)
                    <span class="badge bg-danger rounded-pill">{{ $notificationCount }}</span>
                @endif
            </a>
        </div>

        <div class="storefront-account-nav-slide">
            <a class="nav-link d-flex align-items-center justify-content-center gap-2 @if (request()->routeIs('shop.account.reviews.*')) active @endif"
                href="{{ route('shop.account.reviews.index') }}"
                @if (request()->routeIs('shop.account.reviews.*')) aria-current="page" @endif>
                <i class="bi bi-star" aria-hidden="true"></i>
                <span>{{ __('shop.reviews.my_reviews') }}</span>
            </a>
        </div>

        <div class="storefront-account-nav-slide">
            <a class="nav-link d-flex align-items-center justify-content-center gap-2 @if (request()->routeIs('shop.wishlist.*')) active @endif"
                href="{{ route('shop.wishlist.index') }}"
                @if (request()->routeIs('shop.wishlist.*')) aria-current="page" @endif>
                <i class="bi bi-heart" aria-hidden="true"></i>
                <span>{{ __('shop.wishlist.title') }}</span>

                @if (($storefrontWishlistCount ?? 0) > 0)
                    <span class="badge bg-danger rounded-pill">{{ $storefrontWishlistCount }}</span>
                @endif
            </a>
        </div>

        <div class="storefront-account-nav-slide">
            <a class="nav-link d-flex align-items-center justify-content-center gap-2 @if (request()->routeIs('customer.account.password.*')) active @endif"
                href="{{ route('customer.account.password.edit') }}"
                @if (request()->routeIs('customer.account.password.*')) aria-current="page" @endif>
                <i class="bi bi-shield-lock" aria-hidden="true"></i>
                <span>{{ __('shop.account.profile.change_password') }}</span>
            </a>
        </div>

        <div class="storefront-account-nav-slide">
            <form method="POST" action="{{ route('customer.logout') }}">
                @csrf
                <input type="hidden" name="return_to" value="{{ url()->full() }}">

                <button type="submit"
                    class="nav-link d-flex align-items-center justify-content-center gap-2 border-0 bg-transparent w-100">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                    <span>{{ __('shop.account.profile.logout') }}</span>
                </button>
            </form>
        </div>
    </div>

</nav>
