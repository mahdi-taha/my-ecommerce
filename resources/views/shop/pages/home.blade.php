@extends('shop.layouts.app')

@section('title', __('shop.home.title'))
@section('meta_description', __('shop.home.meta_description'))
@section('canonical', route('shop.home'))
@section('open_graph', 'enabled')

@section('content')

    @include('shop.components.hero')
    @include('shop.components.services')
    @include('shop.components.categories')

    @php
        $productTabs = [
            'all-products' => ['label' => __('shop.home.all_products'), 'products' => $allProducts],
            'new-arrivals' => ['label' => __('shop.home.new_arrivals'), 'products' => $newProducts],
            'featured-products' => ['label' => __('shop.home.featured'), 'products' => $featuredProducts],
        ];

        if ($onSaleProducts->isNotEmpty()) {
            $productTabs['on-sale-products'] = [
                'label' => __('shop.home.on_sale'),
                'products' => $onSaleProducts,
            ];
        }

        $productTabs['top-selling'] = [
            'label' => __('shop.home.top_selling'),
            'products' => $topSellingProducts,
        ];
    @endphp

    <section class="container-fluid py-5">
        <div class="container">
            <div class="text-center mx-auto mb-4" style="max-width: 600px;">
                <h2 class="display-6">{{ __('shop.home.products_title') }}</h2>
                <p class="text-muted mb-0">{{ __('shop.home.products_subtitle') }}</p>
            </div>

            <ul class="nav nav-pills justify-content-center gap-2 mb-4" id="homeProductTabs" role="tablist">
                @foreach ($productTabs as $tabId => $tab)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link @if ($loop->first) active @endif"
                            id="{{ $tabId }}-tab"
                            data-bs-toggle="pill"
                            data-bs-target="#{{ $tabId }}"
                            type="button"
                            role="tab"
                            aria-controls="{{ $tabId }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                            {{ $tab['label'] }}
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content" id="homeProductTabsContent">
                @foreach ($productTabs as $tabId => $tab)
                    <div class="tab-pane fade @if ($loop->first) show active @endif"
                        id="{{ $tabId }}"
                        role="tabpanel"
                        aria-labelledby="{{ $tabId }}-tab"
                        tabindex="0">
                        @if ($tab['products']->isEmpty())
                            <div class="text-center text-muted py-5">
                                {{ __('shop.home.no_products_found') }}
                            </div>
                        @else
                            <div class="row g-4">
                                @foreach ($tab['products'] as $product)
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
