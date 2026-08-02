@extends('shop.layouts.app')

@section('title', __('shop.listing.title'))

@section('meta')
    <meta name="description" content="{{ __('shop.listing.meta_description') }}">
    <link rel="canonical" href="{{ route('shop.products.index') }}">
@endsection

@section('content')
    <section class="container-fluid py-5 bg-light">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h1 class="display-6 mb-1">{{ __('shop.listing.title') }}</h1>
                    <p class="text-muted mb-0">{{ __('shop.listing.results', ['count' => $products->total()]) }}</p>
                </div>
                <form method="GET" action="{{ route('shop.products.index') }}" class="d-flex align-items-center gap-2">
                    @foreach (request()->except(['sort', 'page']) as $name => $value)
                        @if (is_scalar($value))
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
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
