@php
    $categories = [
        ['image' => 'shop/img/avatar.jpg', 'name' => 'shop.categories.electronics', 'count' => 125],
        ['image' => 'shop/img/product-banner-2.jpg', 'name' => 'shop.categories.fashion', 'count' => 84],
        ['image' => 'shop/img/avatar.jpg', 'name' => 'shop.categories.electronics', 'count' => 125],
        ['image' => 'shop/img/product-banner-2.jpg', 'name' => 'shop.categories.fashion', 'count' => 84],
        ['image' => 'shop/img/avatar.jpg', 'name' => 'shop.categories.electronics', 'count' => 125],
        ['image' => 'shop/img/product-banner-2.jpg', 'name' => 'shop.categories.fashion', 'count' => 84],
        ['image' => 'shop/img/avatar.jpg', 'name' => 'shop.categories.electronics', 'count' => 125],
        ['image' => 'shop/img/product-banner-2.jpg', 'name' => 'shop.categories.fashion', 'count' => 84],
        ['image' => 'shop/img/avatar.jpg', 'name' => 'shop.categories.electronics', 'count' => 125],
        ['image' => 'shop/img/product-banner-2.jpg', 'name' => 'shop.categories.fashion', 'count' => 84],
        ['image' => 'shop/img/avatar.jpg', 'name' => 'shop.categories.electronics', 'count' => 125],
        ['image' => 'shop/img/product-banner-2.jpg', 'name' => 'shop.categories.fashion', 'count' => 84],
    ];
@endphp

<div class="container-fluid py-5 bg-light">
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width:600px;">
            <h1 class="display-6">{{ __('shop.categories.title') }}</h1>
            <p class="text-muted">
                {{ __('shop.categories.subtitle') }}
            </p>
        </div>

        <div class="row g-4">
            @foreach ($categories as $category)
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="#" class="category-card text-center d-block">
                        <div class="category-icon">
                            <img src="{{ asset($category['image']) }}" alt="{{ __($category['name']) }}">
                        </div>

                        <h5 class="mt-3 mb-1">{{ __($category['name']) }}</h5>

                        <small class="text-muted">
                            {{ __('shop.categories.products_count', ['count' => $category['count']]) }}
                        </small>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>
