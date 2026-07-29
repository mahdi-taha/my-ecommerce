<div class="container-fluid nav-bar p-0">
    <div class="row gx-0 bg-primary px-5 align-items-center">

        @include('shop.components.category-menu')

        <div class="col-12 col-lg-9">

            <nav class="navbar navbar-expand-lg navbar-light bg-primary">

                <a href="" class="navbar-brand d-block d-lg-none">
                    <h1 class="display-5 text-secondary m-0">
                        <i class="fas fa-shopping-bag text-white me-2"></i>
                        {{ __('shop.navigation.brand') }}
                    </h1>
                </a>

                <button class="navbar-toggler ms-auto"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#navbarCollapse"
                        aria-label="{{ __('shop.navigation.toggle_navigation') }}">

                    <span class="fa fa-bars fa-1x"></span>

                </button>

                <div class="collapse navbar-collapse" id="navbarCollapse">

                    <div class="navbar-nav ms-auto py-0">

                        <a href="index.html" class="nav-item nav-link active">
                            {{ __('shop.navigation.home') }}
                        </a>

                        <a href="shop.html" class="nav-item nav-link">
                            {{ __('shop.navigation.shop') }}
                        </a>

                        <a href="single.html" class="nav-item nav-link">
                            {{ __('shop.navigation.single_page') }}
                        </a>

                        <div class="nav-item dropdown">

                            <a href="#"
                               class="nav-link dropdown-toggle"
                               data-bs-toggle="dropdown">

                                {{ __('shop.navigation.pages') }}

                            </a>

                            <div class="dropdown-menu m-0">

                                <a href="bestseller.html" class="dropdown-item">
                                    {{ __('shop.navigation.bestseller') }}
                                </a>

                                <a href="{{ route('shop.cart.index') }}"
                                   class="dropdown-item d-flex align-items-center justify-content-between gap-3">
                                    <span>{{ __('shop.navigation.cart') }}</span>
                                    <span class="badge bg-secondary rounded-pill">
                                        {{ $storefrontCartQuantity ?? 0 }}
                                    </span>
                                </a>

                                <a href="{{ route('shop.checkout.show') }}" class="dropdown-item">
                                    {{ __('shop.navigation.checkout') }}
                                </a>

                                <a href="404.html" class="dropdown-item">
                                    {{ __('shop.navigation.not_found') }}
                                </a>

                            </div>

                        </div>

                        <a href="contact.html" class="nav-item nav-link me-2">
                            {{ __('shop.navigation.contact') }}
                        </a>

                        {{-- Mobile Categories --}}
                        <div class="nav-item dropdown d-block d-lg-none mb-3">

                            <a href="#"
                               class="nav-link dropdown-toggle"
                               data-bs-toggle="dropdown">

                                {{ __('shop.navigation.all_categories') }}

                            </a>

                            <div class="dropdown-menu m-0">

                                <ul class="list-unstyled categories-bars">

                                    <li>
                                        <div class="categories-bars-item">
                                            <a href="#">{{ __('shop.categories.accessories') }}</a>
                                            <span>(3)</span>
                                        </div>
                                    </li>

                                    <li>
                                        <div class="categories-bars-item">
                                            <a href="#">{{ __('shop.categories.electronics_computers') }}</a>
                                            <span>(5)</span>
                                        </div>
                                    </li>

                                    <li>
                                        <div class="categories-bars-item">
                                            <a href="#">{{ __('shop.categories.laptops_desktops') }}</a>
                                            <span>(2)</span>
                                        </div>
                                    </li>

                                    <li>
                                        <div class="categories-bars-item">
                                            <a href="#">{{ __('shop.categories.mobiles_tablets') }}</a>
                                            <span>(8)</span>
                                        </div>
                                    </li>

                                    <li>
                                        <div class="categories-bars-item">
                                            <a href="#">{{ __('shop.categories.smartphones_tvs') }}</a>
                                            <span>(5)</span>
                                        </div>
                                    </li>

                                </ul>

                            </div>

                        </div>

                    </div>

                    <a href=""
                       class="btn btn-secondary rounded-pill py-2 px-4 px-lg-3 mb-3 mb-md-3 mb-lg-0">

                        <i class="fa fa-mobile-alt me-2"></i>
                        {{ __('shop.navigation.contact_phone') }}

                    </a>

                </div>

            </nav>

        </div>

    </div>
</div>
