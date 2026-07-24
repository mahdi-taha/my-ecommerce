@php
    $translation = $product->translations->first();
    $category = $product->categories->first();
    $categoryTranslation = $category?->translations->first();
    $imageUrl = $product->mainImageUrl();
    $discountPercentage = $product->discountPercentage();
    $currencyCode = $attributes->get('currency-code') ?? setting('currency.default_currency', 'USD');
    $taxMode = $attributes->get('tax-mode') ?? setting('tax.tax_mode', 'b2c');
    $defaultTax = $attributes->get('default-tax');
    $displayPrice = $product->displayPrice($taxMode, $defaultTax);
    $displayRegularPrice = $product->displayRegularPrice($taxMode, $defaultTax);
    $effectiveTaxRate = $product->effectiveTaxRate($defaultTax);
    $formattedTaxRate = rtrim(rtrim(number_format($effectiveTaxRate, 4, '.', ''), '0'), '.');
    $productUrl = $translation?->url_key
        ? route('shop.products.show', ['url_key' => $translation->url_key])
        : '#';
@endphp

<div class="col-lg-3 col-md-4 col-sm-6">
    <article class="product-item bg-white border rounded shadow-sm position-relative overflow-hidden h-100 d-flex flex-column">
        @if ($product->is_new)
            <span class="badge bg-primary position-absolute top-0 start-0 m-2 px-3 py-2">
                {{ __('shop.product.new') }}
            </span>
        @endif

        @if ($discountPercentage !== null)
            <span class="badge bg-danger position-absolute top-0 end-0 m-2 px-3 py-2">
                -{{ $discountPercentage }}%
            </span>
        @endif

        <a href="{{ $productUrl }}" class="d-block text-decoration-none">
            <div class="text-center p-4">
                @if ($imageUrl)
                    <img src="{{ $imageUrl }}"
                        alt="{{ $translation?->name ?? $product->sku }}"
                        class="img-fluid"
                        style="height: 240px; object-fit: contain;">
                @else
                    <div class="d-flex align-items-center justify-content-center bg-light text-muted rounded"
                        style="height: 240px;">
                        <i class="bi bi-image fs-1"></i>
                    </div>
                @endif
            </div>
        </a>

        <div class="px-3 pb-3 d-flex flex-column flex-grow-1">
            @if ($categoryTranslation)
                <small class="text-muted d-block mb-1">
                    <a href="#" class="text-decoration-none text-muted">
                        {{ $categoryTranslation->name }}
                    </a>
                </small>
            @endif

            <h6 class="product-title mb-2">
                <a href="{{ $productUrl }}" class="text-dark text-decoration-none">
                    {{ $translation?->name ?? $product->sku }}
                </a>
            </h6>

            <div class="mb-3">
                <span class="fw-bold fs-5 text-primary">
                    {{ format_store_price($displayPrice, $currencyCode) }}
                </span>

                @if ($product->hasActiveSpecialPrice())
                    <span class="text-muted text-decoration-line-through ms-2">
                        {{ format_store_price($displayRegularPrice, $currencyCode) }}
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

            @if ($product->is_featured)
                <small class="text-primary d-block mb-3">
                    <i class="bi bi-star-fill me-1"></i>
                    {{ __('shop.product.featured') }}
                </small>
            @endif

            <div class="d-flex gap-2 mt-auto">
                <button type="button" class="btn btn-outline-danger"
                    aria-label="{{ __('shop.product.add_to_wishlist') }}">
                    <i class="bi bi-heart"></i>
                </button>

                <button type="button" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-cart-plus me-2"></i>
                    {{ __('shop.product.add_to_cart') }}
                </button>
            </div>
        </div>
    </article>
</div>
