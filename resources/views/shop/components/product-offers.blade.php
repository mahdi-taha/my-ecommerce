<div class="container-fluid py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6">
                    <a href="#" class="d-flex align-items-center justify-content-between border bg-white rounded p-4">
                        <div>
                            <p class="text-muted mb-3">{{ __('shop.offers.camera_message') }}</p>
                            <h3 class="text-primary">{{ __('shop.offers.camera_name') }}</h3>
                            <h1 class="display-3 text-secondary mb-0">{{ __('shop.offers.camera_discount') }} <span
                                    class="text-primary fw-normal">{{ __('shop.offers.off') }}</span></h1>
                        </div>
                        <img src="{{ asset('shop/img/product-1.png') }}" class="img-fluid"
                            alt="{{ __('shop.offers.camera_alt') }}">
                    </a>
                </div>
                <div class="col-lg-6 ">
                    <a href="#" class="d-flex align-items-center justify-content-between border bg-white rounded p-4">
                        <div>
                            <p class="text-muted mb-3">{{ __('shop.offers.watch_message') }}</p>
                            <h3 class="text-primary">{{ __('shop.offers.watch_name') }}</h3>
                            <h1 class="display-3 text-secondary mb-0">{{ __('shop.offers.watch_discount') }} <span
                                    class="text-primary fw-normal">{{ __('shop.offers.off') }}</span></h1>
                        </div>
                        <img src="{{ asset('shop/img/product-2.png') }}" class="img-fluid"
                            alt="{{ __('shop.offers.watch_alt') }}">
                    </a>
                </div>
            </div>
        </div>
    </div>
