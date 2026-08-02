<div class="container-fluid nav-bar p-0 storefront-navbar">
    <div class="row gx-0 bg-primary px-5 align-items-center">
        @include('shop.components.category-menu')

        <div class="col-12 col-lg-9">
            <nav class="navbar navbar-expand-lg navbar-light bg-primary">
                <a href="{{ route('shop.home') }}" class="navbar-brand d-block d-lg-none">
                    <h1 class="display-5 text-secondary m-0">
                        <i class="fas fa-shopping-bag text-white me-2" aria-hidden="true"></i>
                        <bdi>{{ $navbarStoreName }}</bdi>
                    </h1>
                </a>

                <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false"
                    aria-label="{{ __('shop.navigation.toggle_navigation') }}">
                    <span class="fa fa-bars fa-1x" aria-hidden="true"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <div class="navbar-nav ms-auto py-0">
                        <a href="{{ route('shop.home') }}"
                            class="nav-item nav-link {{ request()->routeIs('shop.home') ? 'active' : '' }}"
                            @if (request()->routeIs('shop.home')) aria-current="page" @endif>
                            {{ __('shop.navigation.home') }}
                        </a>

                        <a href="{{ route('shop.products.index') }}"
                            class="nav-item nav-link {{ request()->routeIs('shop.products.index') ? 'active' : '' }}"
                            @if (request()->routeIs('shop.products.index')) aria-current="page" @endif>
                            {{ __('shop.navigation.shop') }}
                        </a>

                        @if($storefrontContactPage)
                            <a href="{{ route('shop.pages.show',['slug' => $storefrontContactPage->translations->first()->slug]) }}" class="nav-item nav-link me-lg-2 {{ request()->routeIs('shop.pages.show') && request()->route('slug') === $storefrontContactPage->translations->first()->slug ? 'active' : '' }}">{{ __('shop.navigation.contact') }}</a>
                        @endif

                        <div class="nav-item d-block d-lg-none" data-category-navigation-mobile>
                            <button class="nav-link border-0 bg-transparent w-100 text-start"
                                id="mobileCategoriesToggle" type="button" data-bs-toggle="collapse"
                                data-bs-target="#mobileCategoriesMenu" aria-expanded="false"
                                aria-controls="mobileCategoriesMenu">
                                {{ __('shop.navigation.all_categories') }}
                            </button>
                            <div class="collapse" id="mobileCategoriesMenu" aria-labelledby="mobileCategoriesToggle">
                                <div class="storefront-mobile-category-browser px-3 pb-3">
                                    <button class="btn btn-sm btn-link px-0 text-decoration-none" type="button"
                                        data-mobile-category-back hidden>
                                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                                        {{ __('shop.navigation.back') }}
                                    </button>
                                    <nav aria-label="{{ __('shop.navigation.category_children') }}">
                                        <ol class="breadcrumb small mb-2" data-mobile-category-breadcrumb></ol>
                                    </nav>
                                    <div class="storefront-category-list" id="mobileCategoryLevel"
                                        data-mobile-category-level></div>
                                </div>
                            </div>
                        </div>

                        <a href="{{ route('shop.cart.index') }}"
                            class="nav-item nav-link d-flex d-lg-none align-items-center gap-2"
                            data-storefront-cart-link>
                            <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                            <span>{{ __('shop.navigation.cart') }}</span>
                            @if (($storefrontCartQuantity ?? 0) > 0)
                                <span class="badge bg-secondary rounded-pill">{{ $storefrontCartQuantity }}</span>
                            @else
                                <span class="badge bg-secondary rounded-pill d-none">0</span>
                            @endif
                        </a>

                        <a href="{{ auth('customer')->check()
                            ? route('shop.wishlist.index')
                            : route('customer.login', ['return_to' => url()->full()]) }}"
                            class="nav-item nav-link d-flex d-lg-none align-items-center gap-2"
                            data-storefront-wishlist-link>
                            <i class="fas fa-heart" aria-hidden="true"></i>
                            <span>{{ __('shop.wishlist.title') }}</span>
                            @if (($storefrontWishlistCount ?? 0) > 0)
                                <span class="badge bg-secondary rounded-pill">{{ $storefrontWishlistCount }}</span>
                            @else
                                <span class="badge bg-secondary rounded-pill d-none">0</span>
                            @endif
                        </a>

                        <div class="nav-item dropdown d-block d-lg-none">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <i class="fa fa-user me-2" aria-hidden="true"></i>
                                <bdi>{{ auth('customer')->user()?->name ?: __('shop.topbar.guest') }}</bdi>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end rounded">
                                @auth('customer')
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
                                    <a href="{{ route('shop.account.notifications.index') }}" class="dropdown-item">
                                        {{ __('shop.notifications.title') }}
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
                                @endauth
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </div>
</div>
