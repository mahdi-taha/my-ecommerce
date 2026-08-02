@extends('shop.layouts.app')

@section('title', $categoryTranslation?->meta_title ?: ($categoryTranslation?->name ?? __('shop.listing.title')))

@section('meta_description', $categoryTranslation?->meta_description ?: ($categoryTranslation ? __('shop.listing.category_meta_description', ['category' => $categoryTranslation->name]) : __('shop.listing.meta_description')))
@section('canonical', $canonicalUrl)
@section('open_graph', 'enabled')
@if ($categoryBannerUrl)
    @section('open_graph_image', $categoryBannerUrl)
@endif

@section('content')
    <section class="container-fluid py-5 bg-light">
        <div class="container">
            @if ($category)
                <nav aria-label="{{ __('shop.listing.breadcrumbs') }}" class="mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('shop.home') }}">{{ __('shop.navigation.home') }}</a></li>
                        @foreach ($categoryBreadcrumbs as $breadcrumbCategory)
                            @php($breadcrumbTranslation = $breadcrumbCategory->translations->first())
                            <li class="breadcrumb-item {{ $breadcrumbCategory->is($category) ? 'active' : '' }}"
                                @if ($breadcrumbCategory->is($category)) aria-current="page" @endif>
                                @if ($breadcrumbCategory->is($category))
                                    {{ $breadcrumbTranslation->name }}
                                @else
                                    <a href="{{ route('shop.categories.show', $breadcrumbTranslation->slug) }}">{{ $breadcrumbTranslation->name }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
                @if ($categoryBannerUrl)
                    <section class="storefront-category-hero rounded mb-4" data-category-hero>
                        <img src="{{ $categoryBannerUrl }}" alt="{{ $categoryTranslation->name }}"
                            class="storefront-category-hero-image">
                        <div class="storefront-category-hero-overlay">
                            <h1 class="display-6 text-white mb-0 storefront-category-hero-title">
                                {{ $categoryTranslation->name }}
                            </h1>
                        </div>
                    </section>
                @endif
            @endif
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    @if (! $categoryBannerUrl)
                        <h1 class="display-6 mb-1">{{ $categoryTranslation?->name ?? __('shop.listing.title') }}</h1>
                    @endif
                    <p class="text-muted mb-0">{{ __('shop.listing.results', ['count' => $products->total()]) }}</p>
                </div>
                <form method="GET" action="{{ $listingAction }}" class="d-flex align-items-center gap-2">
                    @foreach (request()->except(['sort', 'page']) as $name => $value)
                        @if (is_scalar($value))
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                        @elseif ($name === 'attributes' && is_array($value))
                            @foreach ($value as $attributeCode => $optionCodes)
                                @foreach ((array) $optionCodes as $optionCode)
                                    <input type="hidden" name="attributes[{{ $attributeCode }}][]" value="{{ $optionCode }}">
                                @endforeach
                            @endforeach
                        @endif
                    @endforeach
                    <label for="shop-sort" class="form-label mb-0 text-nowrap">{{ __('shop.listing.sort_by') }}</label>
                    <select id="shop-sort" name="sort" class="form-select" onchange="this.form.submit()">
                        @foreach ([
                            'newest' => __('shop.listing.sort.newest'),
                            'oldest' => __('shop.listing.sort.oldest'),
                            'price_asc' => __('shop.listing.sort.price_asc'),
                            'price_desc' => __('shop.listing.sort.price_desc'),
                            'name_asc' => __('shop.listing.sort.name_asc'),
                            'name_desc' => __('shop.listing.sort.name_desc'),
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['sort'] ?? 'newest') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <noscript><button class="btn btn-primary" type="submit">{{ __('shop.listing.submit_sort') }}</button></noscript>
                </form>
            </div>

            <div class="row g-4">
                <div class="col-12 col-lg-3">
                    @include('shop.components.product-listing-filters')
                </div>
                <div class="col-12 col-lg-9">
                    @if ($products->isEmpty())
                        <div class="bg-white border rounded text-center text-muted py-5">
                            {{ __('shop.listing.empty') }}
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
                        <div class="mt-4">{{ $products->links('pagination::bootstrap-5') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
