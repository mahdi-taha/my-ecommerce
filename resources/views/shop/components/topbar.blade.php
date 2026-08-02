<div class="container-fluid px-5 d-none border-bottom d-lg-block storefront-topbar">
    <div class="row gx-0 align-items-center">
        <div class="col-lg-4 text-center text-lg-start mb-lg-0">
            <div class="d-inline-flex align-items-center storefront-social-links" style="height: 45px;">
                @if (filled($topbarFacebookUrl))
                    <a href="{{ $topbarFacebookUrl }}" class="text-muted me-2" target="_blank" rel="noopener noreferrer"
                        aria-label="{{ __('shop.topbar.facebook') }}">
                        <i class="bi bi-facebook" aria-hidden="true"></i>
                    </a>
                @endif
                @if (filled($topbarInstagramUrl))
                    <a href="{{ $topbarInstagramUrl }}" class="text-muted mx-2" target="_blank"
                        rel="noopener noreferrer" aria-label="{{ __('shop.topbar.instagram') }}">
                        <i class="bi bi-instagram" aria-hidden="true"></i>
                    </a>
                @endif
                @if (filled($topbarWhatsAppUrl))
                    <a href="{{ $topbarWhatsAppUrl }}" class="text-muted ms-2" target="_blank" rel="noopener noreferrer"
                        aria-label="{{ __('shop.topbar.whatsapp') }}">
                        <i class="bi bi-whatsapp" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </div>

        <div class="col-lg-4 text-center d-flex align-items-center justify-content-center">
            @if (filled($topbarPhone))
                <small class="text-dark me-1">{{ __('shop.topbar.call_us') }}</small>
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $topbarPhone) }}" class="text-muted storefront-phone">
                    <bdi dir="ltr">{{ $topbarPhone }}</bdi>
                </a>
            @endif
        </div>

        <div class="col-lg-4 text-center text-lg-end">
            <div class="d-inline-flex align-items-center" style="height: 45px;">
                <div class="text-muted me-2">
                    <small>{{ __('shop.topbar.currency_label') }} <bdi dir="ltr">{{ $topbarCurrencyCode }}</bdi></small>
                </div>

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

                <div class="dropdown">
                    <button class="btn btn-link dropdown-toggle text-muted ms-2 p-0 text-decoration-none" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        aria-label="{{ __('shop.topbar.customer_menu') }}">
                        <small>
                            <i class="fa fa-user me-2" aria-hidden="true"></i>
                            <bdi>{{ $topbarCustomer?->name ?: __('shop.topbar.guest') }}</bdi>
                        </small>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end rounded">
                        @if ($topbarCustomer)
                            <a href="{{ route('customer.account.edit') }}" class="dropdown-item">
                                {{ __('shop.account.navigation.profile') }}
                            </a>
                            <a href="{{ route('customer.addresses.index') }}" class="dropdown-item">
                                {{ __('shop.account.navigation.address_book') }}
                            </a>
                            <a href="{{ route('shop.account.orders.index') }}" class="dropdown-item">
                                {{ __('shop.account.orders.my_orders') }}
                            </a>
                            <a href="{{ route('shop.wishlist.index') }}" class="dropdown-item">
                                {{ __('shop.wishlist.title') }}
                            </a>
                            <a href="{{ route('shop.account.notifications.index') }}"
                                class="dropdown-item d-flex align-items-center justify-content-between gap-3">
                                <span>{{ __('shop.notifications.title') }}</span>
                                @if ($topbarNotificationCount > 0)
                                    <span class="badge bg-danger rounded-pill">{{ $topbarNotificationCount }}</span>
                                @endif
                            </a>
                            <a href="{{ route('customer.account.password.edit') }}" class="dropdown-item">
                                {{ __('shop.account.profile.change_password') }}
                            </a>
                            <form method="POST" action="{{ route('customer.logout') }}">
                                @csrf
                                <input type="hidden" name="return_to" value="{{ url()->full() }}">
                                <button type="submit" class="dropdown-item">
                                    {{ __('shop.account.profile.logout') }}
                                </button>
                            </form>
                        @else
                            <a href="{{ route('customer.login', ['return_to' => url()->full()]) }}" class="dropdown-item">
                                {{ __('shop.auth.login.submit') }}
                            </a>
                            <a href="{{ route('customer.register', ['return_to' => url()->full()]) }}" class="dropdown-item">
                                {{ __('shop.auth.register.title') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-5 py-4 d-none d-lg-block storefront-header-main">
    <div class="row gx-0 align-items-center text-center">
        <div class="col-md-4 col-lg-3 text-center text-lg-start">
            <div class="d-inline-flex align-items-center">
                <a href="{{ route('shop.home') }}" class="navbar-brand p-0">
                    @if ($topbarLogoUrl)
                        <img src="{{ $topbarLogoUrl }}"
                            alt="{{ __('shop.topbar.store_logo', ['store' => $topbarStoreName]) }}" class="img-fluid"
                            style="max-height: 70px;">
                    @else
                        <h1 class="display-5 text-primary m-0">
                            <bdi>{{ $topbarStoreName }}</bdi>
                        </h1>
                    @endif
                </a>
            </div>
        </div>

        <div class="col-md-4 col-lg-6 text-center">
            <div class="position-relative ps-4">
                <form method="GET" action="{{ route('shop.products.index') }}" class="d-flex border rounded-pill"
                    role="search">
                    <input class="form-control border-0 rounded-pill w-100 py-3" type="search" name="q"
                        value="{{ request('q') }}" placeholder="{{ __('shop.navigation.search_placeholder') }}"
                        aria-label="{{ __('shop.navigation.search_label') }}">
                    <button type="submit" class="btn btn-primary rounded-pill py-3 px-5"
                        style="border: 0;" aria-label="{{ __('shop.navigation.search_label') }}">
                        <i class="fas fa-search" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
        </div>

        <div class="col-md-4 col-lg-3 text-center text-lg-end">
            <div class="d-inline-flex align-items-center">
                <a href="{{ $topbarCustomer
                    ? route('shop.wishlist.index')
                    : route('customer.login', ['return_to' => url()->full()]) }}"
                    class="text-muted d-flex align-items-center justify-content-center me-3 position-relative"
                    data-storefront-wishlist-link
                    aria-label="{{ __('shop.topbar.wishlist') }}">
                    <span class="rounded-circle btn-md-square border">
                        <i class="fas fa-heart" aria-hidden="true"></i>
                    </span>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger storefront-header-badge {{ ! $topbarCustomer || ($storefrontWishlistCount ?? 0) < 1 ? 'd-none' : '' }}"
                        >{{ $topbarCustomer ? ($storefrontWishlistCount ?? 0) : 0 }}</span>
                </a>
                <a href="{{ route('shop.cart.index') }}"
                    class="text-muted d-flex align-items-center justify-content-center position-relative"
                    data-storefront-cart-link
                    aria-label="{{ __('shop.topbar.cart') }}">
                    <span class="rounded-circle btn-md-square border">
                        <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                    </span>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger storefront-header-badge {{ ($storefrontCartQuantity ?? 0) < 1 ? 'd-none' : '' }}"
                        >{{ $storefrontCartQuantity ?? 0 }}</span>
                </a>
            </div>
        </div>
    </div>
</div>
