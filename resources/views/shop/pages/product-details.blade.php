@extends('shop.layouts.app')

@section('title', $translation->name)

@section('content')
    <!-- Single Products Start -->
    <div class="container-fluid shop py-5">
        <div class="container py-5">
            <nav aria-label="{{ __('shop.product_details.breadcrumb_label') }}" class="mb-4">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="{{ route('shop.home') }}">{{ __('shop.navigation.home') }}</a>
                    </li>
                    @foreach ($breadcrumbCategories as $breadcrumbCategory)
                        <li class="breadcrumb-item">
                            <a href="#">{{ $breadcrumbCategory->translations->first()->name }}</a>
                        </li>
                    @endforeach
                    <li class="breadcrumb-item active" aria-current="page">
                        {{ $translation->name }}
                    </li>
                </ol>
            </nav>

            <div class="row g-4">
                <div class="col-lg-12 col-xl-12">
                    <div class="row g-4 single-product">
                        <div class="col-xl-6" style="direction: ltr;">
                            <div class="single-carousel owl-carousel">
                                @forelse ($galleryImages as $image)
                                    <div class="single-item"
                                        data-dot="<img class='img-fluid' src='{{ $image['url'] }}' alt=''>">
                                        <div class="single-inner bg-light rounded">
                                            <img src="{{ $image['url'] }}" class="img-fluid rounded"
                                                alt="{{ $translation->name }}">
                                        </div>
                                    </div>
                                @empty
                                    <div class="single-item">
                                    <div class="single-inner bg-light rounded">
                                            <div class="text-muted text-center">
                                                <i class="bi bi-image fs-1 d-block mb-2"></i>
                                                {{ __('shop.product_details.image_unavailable') }}
                                            </div>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div class="col-xl-6">
                            <div class="ps-xl-4">
                                <h1 class="h2 fw-bold mb-3">{{ $translation->name }}</h1>
                                @if ($category?->translations->first())
                                    <p class="text-muted mb-3">
                                        {{ __('shop.product_details.category_label') }}
                                        <a href="#" class="text-decoration-none">
                                            {{ $category->translations->first()->name }}
                                        </a>
                                    </p>
                                @endif
                                @php
                                    $effectiveTaxRate = $product->effectiveTaxRate($defaultTax);
                                    $formattedTaxRate = rtrim(rtrim(number_format($effectiveTaxRate, 4, '.', ''), '0'), '.');
                                @endphp
                                <div class="mb-4">
                                    <span class="h4 fw-bold text-primary mb-0">
                                        {{ format_store_price($product->displayPrice($taxMode, $defaultTax), $currencyCode) }}
                                    </span>
                                    @if ($product->hasActiveSpecialPrice())
                                        <span class="text-muted text-decoration-line-through ms-2">
                                            {{ format_store_price($product->displayRegularPrice($taxMode, $defaultTax), $currencyCode) }}
                                        </span>
                                    @endif
                                    @if ($effectiveTaxRate > 0)
                                        <small class="d-block text-muted mt-1">
                                            {{ $taxMode === 'b2c'
                                                ? __('shop.product_details.including_tax', ['rate' => $formattedTaxRate])
                                                : __('shop.product_details.tax_at_checkout', ['rate' => $formattedTaxRate]) }}
                                        </small>
                                    @endif
                                </div>

                                <div class="mb-3">
                                    <span class="badge {{ $inStock ? 'bg-success' : 'bg-danger' }} rounded-pill px-3 py-2">
                                        <i class="bi {{ $inStock ? 'bi-check-lg' : 'bi-x-lg' }} me-1"></i>
                                        {{ $inStock
                                            ? __('shop.product.available_quantity', ['quantity' => rtrim(rtrim($availableQuantity, '0'), '.')])
                                            : __('shop.product.out_of_stock') }}
                                    </span>
                                </div>

                                @if ($translation->short_description)
                                    <p class="text-muted lh-lg mb-4">
                                        {{ $translation->short_description }}
                                    </p>
                                @endif

                                @if ($specifications->isNotEmpty())
                                    <div class="mb-3">
                                        <dl class="row mb-0">
                                            @foreach ($specifications as $specification)
                                                <dt class="col-sm-4 col-lg-3 py-2">
                                                    {{ $specification['label'] }}
                                                </dt>
                                                <dd class="col-sm-8 col-lg-9 py-2 mb-0 text-muted">
                                                    {{ $specification['value'] }}
                                                </dd>
                                            @endforeach
                                        </dl>
                                    </div>
                                @endif

                                <div class="input-group quantity mb-4" style="width: 140px;">
                                    <div class="input-group-btn">
                                        <button type="button" class="btn btn-sm btn-minus rounded-circle bg-light border"
                                            aria-label="{{ __('shop.product_details.decrease_quantity') }}"
                                            @disabled(! $inStock)>
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </div>
                                    <input type="number" class="form-control form-control-sm text-center border-0"
                                        value="{{ $inStock ? 1 : 0 }}" min="1" max="{{ $availableQuantity }}"
                                        step="1" aria-label="{{ __('shop.product_details.quantity') }}"
                                        @disabled(! $inStock)>
                                    <div class="input-group-btn">
                                        <button type="button" class="btn btn-sm btn-plus rounded-circle bg-light border"
                                            aria-label="{{ __('shop.product_details.increase_quantity') }}"
                                            @disabled(! $inStock)>
                                            <i class="fa fa-plus"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap gap-3">
                                    <a href="#"
                                        class="btn btn-primary border border-secondary rounded-pill px-4 py-2 mb-4 text-primary {{ $inStock ? '' : 'disabled' }}"
                                        @unless ($inStock)
                                            aria-disabled="true" tabindex="-1"
                                        @endunless>
                                        <i class="fa fa-shopping-bag me-2 text-white"></i>
                                        {{ __('shop.product.add_to_cart') }}
                                    </a>
                                    <a href="#"
                                        class="btn btn-primary border border-secondary rounded-pill px-4 py-2 mb-4 text-primary">
                                        <i class="bi bi-heart me-2"></i>
                                        {{ __('shop.product.wishlist') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-5">
                            <section class="border-top pt-5">
                                <h2 class="h4 fw-bold mb-4">{{ __('shop.product_details.description') }}</h2>
                                @if ($translation->description)
                                    <div class="text-muted lh-lg">
                                        {!! nl2br(e($translation->description)) !!}
                                    </div>
                                @endif

                            </section>
                        </div>
                    </div>
                </div>
            </div>

            @if ($relatedProducts->isNotEmpty())
                <section class="pt-5 mt-5 border-top">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h2 class="h3 fw-bold mb-0">{{ __('shop.product_details.related_products') }}</h2>
                    </div>

                    <div class="row g-4">
                        @foreach ($relatedProducts as $relatedProduct)
                            <x-shop.product-card
                                :product="$relatedProduct"
                                :currency-code="$currencyCode"
                                :tax-mode="$taxMode"
                                :default-tax="$defaultTax"
                            />
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
    <!-- Single Products End -->
@endsection
