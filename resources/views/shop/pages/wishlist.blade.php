@extends('shop.layouts.app')

@section('title', __('shop.wishlist.title'))

@section('content')
    <div class="container-fluid py-5">
        <div class="container py-4">
            @include('customer.account._navigation')
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">{{ __('shop.wishlist.title') }}</h1>
            </div>

            @if ($items->isEmpty())
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-5 text-center">
                        <i class="bi bi-heart display-4 text-muted"></i>
                        <h2 class="h5 mt-3">{{ __('shop.wishlist.empty') }}</h2>
                    </div>
                </div>
            @else
                <div class="row g-4">
                    @foreach ($items as $item)
                        @php
                            $product = $item->product;
                            $translation = $product->translations->first();
                            $isSimple = $product->type === \App\Enums\ProductType::Simple->value;
                            $isConfigurable = $product->type === \App\Enums\ProductType::Configurable->value;
                            $isStorefrontEligible = $product->isWishlistEligible() && $translation?->url_key;
                            $isAvailable = $product->isWishlistAvailable();
                            $productUrl = $isStorefrontEligible
                                ? route('shop.products.show', $translation->url_key)
                                : null;
                        @endphp
                        <div class="col-12">
                            <article class="card border-0 shadow-sm">
                                <div class="card-body p-4">
                                    <div class="row g-4 align-items-center">
                                        <div class="col-12 col-sm-3 col-lg-2 text-center">
                                            @if ($product->mainImageUrl())
                                                <img class="img-fluid" style="width: 140px; height: 140px; object-fit: contain;"
                                                    src="{{ $product->mainImageUrl() }}"
                                                    alt="{{ $translation?->name ?? $product->sku }}">
                                            @else
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center mx-auto"
                                                    style="width: 140px; height: 140px;">
                                                    <i class="bi bi-image fs-1 text-muted"></i>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="col-12 col-sm-9 col-lg-5">
                                            <h2 class="h5">{{ $translation?->name ?? $product->sku }}</h2>
                                            <span class="badge {{ $isAvailable ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $isAvailable ? __('shop.wishlist.available') : __('shop.wishlist.unavailable') }}
                                            </span>
                                        </div>
                                        <div class="col-12 col-lg-2">
                                            @if ($isSimple)
                                                <strong class="text-primary fs-5">
                                                    {{ format_store_price($product->displayPrice($taxMode, $defaultTax), $currencyCode) }}
                                                </strong>
                                            @elseif ($isConfigurable)
                                                <span class="text-muted">{{ __('shop.product_details.select_options_for_price') }}</span>
                                            @endif
                                        </div>
                                        <div class="col-12 col-lg-3">
                                            <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                                                @if ($productUrl)
                                                    <a class="btn btn-outline-primary" href="{{ $productUrl }}">
                                                        {{ __('shop.wishlist.view_product') }}
                                                    </a>
                                                @endif
                                                <form method="POST" action="{{ route('shop.wishlist.destroy', $product) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-outline-danger" type="submit">
                                                        <i class="bi bi-heart-fill me-1"></i>
                                                        {{ __('shop.wishlist.remove') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">{{ $items->links() }}</div>
            @endif
        </div>
    </div>
@endsection
