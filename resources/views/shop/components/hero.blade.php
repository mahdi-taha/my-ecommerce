    <!-- Carousel Start -->
    <div class="container-fluid carousel bg-light px-0">
        <div class="row g-0 justify-content-end">
            <div class="col-12 col-lg-7 col-xl-9" style="direction: ltr;">
                <div class="header-carousel owl-carousel bg-light py-5">
                    <div class="row g-0 header-carousel-item align-items-center">
                        <div class="col-xl-6 carousel-img">
                            <img src="{{ asset('shop/img/carousel-1.png') }}" class="img-fluid w-100"
                                alt="{{ __('shop.hero.image_alt') }}">
                        </div>
                        <div class="col-xl-6 carousel-content p-4">
                            <h4 class="text-uppercase fw-bold mb-4 "
                                style="letter-spacing: 3px;">{{ __('shop.hero.first_saving') }}</h4>
                            <h1 class="display-3 text-capitalize mb-4 ">{{ __('shop.hero.selected_products') }}</h1>
                            <p class="text-dark ">{{ __('shop.hero.terms_apply') }}</p>
                            <a class="btn btn-primary rounded-pill py-3 px-5 "
                                href="#">{{ __('shop.hero.shop_now') }}</a>
                        </div>
                    </div>
                    <div class="row g-0 header-carousel-item align-items-center">
                        <div class="col-xl-6 carousel-img">
                            <img src="{{ asset('shop/img/carousel-2.png') }}" class="img-fluid w-100"
                                alt="{{ __('shop.hero.image_alt') }}">
                        </div>
                        <div class="col-xl-6 carousel-content p-4">
                            <h4 class="text-uppercase fw-bold mb-4 "
                                style="letter-spacing: 3px;">{{ __('shop.hero.second_saving') }}</h4>
                            <h1 class="display-3 text-capitalize mb-4 ">{{ __('shop.hero.selected_products') }}</h1>
                            <p class="text-dark ">{{ __('shop.hero.terms_apply') }}</p>
                            <a class="btn btn-primary rounded-pill py-3 px-5 "
                                href="#">{{ __('shop.hero.shop_now') }}</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-5 col-xl-3">
                <div class="carousel-header-banner h-100">
                    <img src="{{ asset('shop/img/header-img.jpg') }}" class="img-fluid w-100 h-100"
                        style="object-fit: cover;" alt="{{ __('shop.hero.image_alt') }}">
                    <div class="carousel-banner-offer">
                        <p class="bg-primary text-white rounded fs-5 py-2 px-4 mb-0 me-3">
                            {{ __('shop.hero.banner_saving') }}
                        </p>
                        <p class="text-primary fs-5 fw-bold mb-0">{{ __('shop.hero.special_offer') }}</p>
                    </div>
                    <div class="carousel-banner">
                        <div class="carousel-banner-content text-center p-4">
                            <a href="#" class="d-block mb-2">{{ __('shop.hero.banner_category') }}</a>
                            <a href="#" class="d-block text-white fs-3">{{ __('shop.hero.banner_product') }}</a>
                            <del class="me-2 text-white fs-5">{{ __('shop.hero.banner_original_price') }}</del>
                            <span class="text-primary fs-5">{{ __('shop.hero.banner_price') }}</span>
                        </div>
                        <a href="#" class="btn btn-primary rounded-pill py-2 px-4"><i
                                class="fas fa-shopping-cart me-2"></i> {{ __('shop.product.add_to_cart') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Carousel End -->
