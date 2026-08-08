@extends('shop.layouts.app')

@section('title', filled($translation->meta_title) ? $translation->meta_title : $translation->name)
@section('meta_description', $productMetaDescription)
@section('canonical', $productCanonicalUrl)
@section('open_graph', 'enabled')
@section('open_graph_type', 'product')
@if (($galleryImages->firstWhere('is_placeholder', false)['url'] ?? null) !== null)
    @section('open_graph_image', $galleryImages->firstWhere('is_placeholder', false)['url'])
@endif
@section('meta')
    <script type="application/ld+json">{!! \Illuminate\Support\Js::encode($productStructuredData) !!}</script>
@endsection

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
                            <a href="{{ route('shop.categories.show', ['slug' => $breadcrumbCategory->translations->first()->slug]) }}">{{ $breadcrumbCategory->translations->first()->name }}</a>
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
                                @foreach ($galleryImages as $image)
                                    <div class="single-item"
                                        data-dot="{!! $image['is_placeholder']
                                            ? '<span class=\'d-flex align-items-center justify-content-center text-muted\'><i class=\'bi bi-image\' aria-hidden=\'true\'></i></span>'
                                            : '<img class=\'img-fluid\' src=\''.e($image['url']).'\' alt=\'\'>' !!}">
                                        <div class="single-inner bg-light rounded">
                                            @if ($image['is_placeholder'])
                                                <img class="img-fluid rounded d-none"
                                                    alt="{{ $translation->name }}" data-product-main-image>
                                                <div class="text-muted text-center" data-product-image-placeholder>
                                                    <i class="bi bi-image fs-1 d-block mb-2"></i>
                                                    {{ __('shop.product_details.image_unavailable') }}
                                                </div>
                                            @else
                                                <img src="{{ $image['url'] }}" class="img-fluid rounded"
                                                    alt="{{ $translation->name }}"
                                                    @if ($loop->first) data-product-main-image @endif>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-xl-6">
                            <div class="ps-xl-4">
                                <h1 class="h2 fw-bold mb-3">{{ $translation->name }}</h1>
                                @if ($category?->translations->first())
                                    <p class="text-muted mb-3">
                                        {{ __('shop.product_details.category_label') }}
                                        <a href="{{ route('shop.categories.show', ['slug' => $category->translations->first()->slug]) }}" class="text-decoration-none">
                                            {{ $category->translations->first()->name }}
                                        </a>
                                    </p>
                                @endif
                                @php
                                    $effectiveTaxRate = $isConfigurable
                                        ? $configurablePriceRange['common_tax_rate'] ?? null
                                        : $product->effectiveTaxRate($defaultTax);
                                    $formattedTaxRate = $effectiveTaxRate === null
                                        ? ''
                                        : rtrim(rtrim(number_format($effectiveTaxRate, 4, '.', ''), '0'), '.');
                                @endphp
                                <div class="mb-4" data-product-price>
                                    @if ($isConfigurable)
                                        @if ($configurablePriceRange)
                                            <span class="h4 fw-bold text-primary mb-0" data-current-price>
                                                {{ format_store_price_range(
                                                    $configurablePriceRange['minimum'],
                                                    $configurablePriceRange['maximum'],
                                                    $currencyCode
                                                ) }}
                                            </span>
                                            @if ($configurablePriceRange['show_regular_range'])
                                                <span class="text-muted text-decoration-line-through ms-2" data-regular-price>
                                                    {{ format_store_price_range(
                                                        $configurablePriceRange['regular_minimum'],
                                                        $configurablePriceRange['regular_maximum'],
                                                        $currencyCode
                                                    ) }}
                                                </span>
                                            @endif
                                            @if ($effectiveTaxRate !== null && $effectiveTaxRate > 0)
                                                <small class="d-block text-muted mt-1" data-tax-label>
                                                    {{ $taxMode === 'b2c'
                                                        ? __('shop.product_details.including_tax', ['rate' => $formattedTaxRate])
                                                        : __('shop.product_details.tax_at_checkout', ['rate' => $formattedTaxRate]) }}
                                                </small>
                                            @endif
                                        @else
                                            <span class="text-muted" data-price-placeholder>
                                                {{ __('shop.product.unavailable') }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="h4 fw-bold text-primary mb-0" data-current-price>
                                            {{ format_store_price($product->displayPrice($taxMode, $defaultTax), $currencyCode) }}
                                        </span>
                                        @if ($product->hasActiveSpecialPrice())
                                            <span class="text-muted text-decoration-line-through ms-2" data-regular-price>
                                                {{ format_store_price($product->displayRegularPrice($taxMode, $defaultTax), $currencyCode) }}
                                            </span>
                                        @endif
                                        @if ($effectiveTaxRate > 0)
                                            <small class="d-block text-muted mt-1" data-tax-label>
                                                {{ $taxMode === 'b2c'
                                                    ? __('shop.product_details.including_tax', ['rate' => $formattedTaxRate])
                                                    : __('shop.product_details.tax_at_checkout', ['rate' => $formattedTaxRate]) }}
                                            </small>
                                        @endif
                                    @endif
                                </div>

                                @if ($isConfigurable)
                                    <p class="text-muted mb-3 d-none" data-variant-sku>
                                        {{ __('shop.product_details.sku') }}
                                        <span class="fw-semibold"></span>
                                    </p>
                                @endif

                                <div class="mb-3" data-product-availability>
                                    <span class="badge {{ $inStock ? 'bg-success' : 'bg-danger' }} rounded-pill px-3 py-2">
                                        <i class="bi {{ $inStock ? 'bi-check-lg' : 'bi-x-lg' }} me-1"></i>
                                        <span data-availability-label>
                                            {{ $isConfigurable
                                                ? ($configurableAttributes->isNotEmpty()
                                                    ? __('shop.product_details.select_options')
                                                    : __('shop.product.unavailable'))
                                                : ($inStock
                                                    ? __('shop.product.available_quantity', ['quantity' => rtrim(rtrim($availableQuantity, '0'), '.')])
                                                    : ($hasPositiveEffectivePrice
                                                        ? __('shop.product.out_of_stock')
                                                        : __('shop.product.unavailable'))) }}
                                        </span>
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

                                <form action="{{ route('shop.cart.items.store') }}" method="POST"
                                    data-storefront-cart-form
                                    data-cart-url="{{ route('shop.cart.index') }}"
                                    data-view-cart-label="{{ __('shop.cart.view_cart') }}"
                                    data-continue-shopping-label="{{ __('shop.cart.continue_shopping') }}"
                                    @if ($isConfigurable)
                                        data-configurable-product-form
                                        data-unavailable-label="{{ $configurableAttributes->isNotEmpty()
                                            ? __('shop.product_details.unavailable_combination')
                                            : __('shop.product.unavailable') }}"
                                        data-select-label="{{ __('shop.product_details.select_options') }}"
                                        data-out-of-stock-label="{{ __('shop.product.out_of_stock') }}"
                                    @endif>
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->getKey() }}">
                                    <input type="hidden" name="product_type"
                                        value="{{ $isConfigurable ? 'configurable' : 'simple' }}">

                                    @if ($isConfigurable)
                                        <div class="mb-4">
                                            @foreach ($configurableAttributes as $configurableAttribute)
                                                @php($attributeInputName = 'options['.$configurableAttribute['id'].']')
                                                @php($attributeInputId = 'configurable_attribute_'.$configurableAttribute['id'])
                                                <div class="mb-3 storefront-configurable-option-group"
                                                    data-configurable-attribute="{{ $configurableAttribute['id'] }}"
                                                    data-configurable-control="{{ $configurableAttribute['swatch_type'] }}">
                                                    @if ($configurableAttribute['swatch_type'] === 'dropdown')
                                                        <label for="{{ $attributeInputId }}" class="form-label fw-semibold">
                                                            {{ $configurableAttribute['label'] }}
                                                        </label>
                                                        <select id="{{ $attributeInputId }}" name="{{ $attributeInputName }}"
                                                            class="form-select @error('options.'.$configurableAttribute['id']) is-invalid @enderror"
                                                            data-configurable-select required>
                                                            <option value="">
                                                                {{ __('shop.product_details.choose_attribute', ['attribute' => $configurableAttribute['label']]) }}
                                                            </option>
                                                            @foreach ($configurableAttribute['options'] as $option)
                                                                <option value="{{ $option['id'] }}" data-configurable-option
                                                                    @selected((string) old('options.'.$configurableAttribute['id']) === (string) $option['id'])>
                                                                    {{ $option['label'] }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <fieldset class="storefront-configurable-options">
                                                            <legend class="form-label fw-semibold">
                                                                {{ $configurableAttribute['label'] }}
                                                            </legend>
                                                            <div class="storefront-configurable-options__items">
                                                                @foreach ($configurableAttribute['options'] as $option)
                                                                    @php($optionInputId = $attributeInputId.'_option_'.$option['id'])
                                                                    <div class="storefront-configurable-option">
                                                                        <input type="radio" id="{{ $optionInputId }}"
                                                                            name="{{ $attributeInputName }}" value="{{ $option['id'] }}"
                                                                            class="storefront-configurable-option__input"
                                                                            data-configurable-option required
                                                                            @checked((string) old('options.'.$configurableAttribute['id']) === (string) $option['id'])>
                                                                        <label for="{{ $optionInputId }}"
                                                                            class="storefront-configurable-option__label storefront-configurable-option__label--{{ $configurableAttribute['swatch_type'] }}">
                                                                            @if ($configurableAttribute['swatch_type'] === 'color')
                                                                                <span class="storefront-configurable-option__swatch {{ $option['swatch_value'] === null ? 'storefront-configurable-option__swatch--missing' : '' }}"
                                                                                    @if ($option['swatch_value'] !== null) style="--storefront-swatch-color: {{ $option['swatch_value'] }}" @endif
                                                                                    aria-hidden="true"></span>
                                                                            @endif
                                                                            <span>{{ $option['label'] }}</span>
                                                                        </label>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </fieldset>
                                                    @endif
                                                    @error('options.'.$configurableAttribute['id'])
                                                        <div class="text-danger mt-2">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            @endforeach
                                            @error('options')
                                                <div class="text-danger mb-3">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    @endif

                                    <div class="input-group quantity mb-4" style="width: 140px;">
                                        <div class="input-group-btn">
                                            <button type="button"
                                                class="btn btn-sm btn-minus rounded-circle bg-light border"
                                                aria-label="{{ __('shop.product_details.decrease_quantity') }}"
                                                @disabled(! $inStock || $isConfigurable)>
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        </div>
                                        <input type="number" name="quantity"
                                            class="form-control form-control-sm text-center border-0 @error('quantity') is-invalid @enderror"
                                            value="{{ old('quantity', $inStock ? 1 : 0) }}" min="1"
                                            max="{{ $availableQuantity }}" step="1"
                                            aria-label="{{ __('shop.product_details.quantity') }}"
                                            @disabled(! $inStock || $isConfigurable)>
                                        <div class="input-group-btn">
                                            <button type="button"
                                                class="btn btn-sm btn-plus rounded-circle bg-light border"
                                                aria-label="{{ __('shop.product_details.increase_quantity') }}"
                                                @disabled(! $inStock || $isConfigurable)>
                                                <i class="fa fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    @error('quantity')
                                        <div class="text-danger mb-3">{{ $message }}</div>
                                    @enderror

                                    <div class="d-flex flex-wrap gap-3">
                                        <button type="submit"
                                            class="btn btn-primary border border-secondary rounded-pill px-4 py-2 mb-4 text-primary"
                                            @disabled(! $inStock || $isConfigurable)>
                                            <i class="fa fa-shopping-bag me-2 text-white"></i>
                                            {{ __('shop.product.add_to_cart') }}
                                        </button>
                                        @auth('customer')
                                            <button type="submit" form="product-wishlist-form"
                                                class="btn btn-primary border border-secondary rounded-pill px-4 py-2 mb-4 text-primary">
                                                <i class="bi {{ $isWishlisted ? 'bi-heart-fill' : 'bi-heart' }} me-2"></i>
                                                {{ $isWishlisted ? __('shop.wishlist.remove') : __('shop.wishlist.add') }}
                                            </button>
                                        @else
                                            <a href="{{ route('customer.login', ['return_to' => url()->full()]) }}"
                                                class="btn btn-primary border border-secondary rounded-pill px-4 py-2 mb-4 text-primary">
                                                <i class="bi bi-heart me-2"></i>
                                                {{ __('shop.wishlist.add') }}
                                            </a>
                                        @endauth
                                    </div>
                                    @if ($isConfigurable)
                                        <script type="application/json" data-configurable-variants>
                                            {!! json_encode(
                                                $variantPresentation,
                                                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
                                            ) !!}
                                        </script>
                                    @endif
                                </form>
                                @auth('customer')
                                    <form id="product-wishlist-form" method="POST"
                                        action="{{ $isWishlisted
                                            ? route('shop.wishlist.destroy', ['product' => $product])
                                            : route('shop.wishlist.store') }}">
                                        @csrf
                                        @if ($isWishlisted)
                                            @method('DELETE')
                                        @else
                                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                                        @endif
                                    </form>
                                @endauth
                            </div>
                        </div>

                        @if (trim((string) $translation->description) !== '')
                            <div class="col-12 mt-5">
                                <section class="border-top pt-5">
                                    <h2 class="h4 fw-bold mb-4">{{ __('shop.product_details.description') }}</h2>
                                    <div class="text-muted lh-lg">
                                        {!! nl2br(e($translation->description)) !!}
                                    </div>
                                </section>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

    @include('shop.components.product-reviews')

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
