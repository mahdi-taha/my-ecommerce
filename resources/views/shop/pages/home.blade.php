@extends('shop.layouts.app')

@section('title', __('shop.home.title'))

@section('content')

    @include('shop.components.hero')
    @include('shop.components.services')
    @include('shop.components.categories')

    <section class="container-fluid py-5">
        <div class="container">
            <div class="text-center mx-auto mb-4" style="max-width: 600px;">
                <h2 class="display-6">{{ __('shop.home.products_title') }}</h2>
                <p class="text-muted mb-0">{{ __('shop.home.products_subtitle') }}</p>
            </div>

            <ul class="nav nav-pills justify-content-center gap-2 mb-4" id="homeProductTabs" role="tablist">
                @foreach ([
                    'all-products' => __('shop.home.all_products'),
                    'new-arrivals' => __('shop.home.new_arrivals'),
                    'featured-products' => __('shop.home.featured'),
                    'top-selling' => __('shop.home.top_selling'),
                ] as $tabId => $tabLabel)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link @if ($loop->first) active @endif"
                            id="{{ $tabId }}-tab"
                            data-bs-toggle="pill"
                            data-bs-target="#{{ $tabId }}"
                            type="button"
                            role="tab"
                            aria-controls="{{ $tabId }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                            {{ $tabLabel }}
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content" id="homeProductTabsContent">
                @foreach ([
                    'all-products' => $allProducts,
                    'new-arrivals' => $newProducts,
                    'featured-products' => $featuredProducts,
                    'top-selling' => $topSellingProducts,
                ] as $tabId => $products)
                    <div class="tab-pane fade @if ($loop->first) show active @endif"
                        id="{{ $tabId }}"
                        role="tabpanel"
                        aria-labelledby="{{ $tabId }}-tab"
                        tabindex="0">
                        @if ($products->isEmpty())
                            <div class="text-center text-muted py-5">
                                {{ __('shop.home.no_products_found') }}
                            </div>
                        @else
                            <div class="row g-4">
                                @foreach ($products as $product)
                                    <x-shop.product-card
                                        :product="$product"
                                        :currency-code="$currencyCode"
                                        :tax-mode="$taxMode"
                                        :default-tax="$defaultTax"
                                    />
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @include('shop.components.product-offers')

@endsection
