<aside class="border rounded bg-white p-4" aria-label="{{ __('shop.listing.filters') }}">
    <form method="GET" action="{{ $listingAction }}">
        <h2 class="h5 mb-4">{{ __('shop.listing.filters') }}</h2>

        <div class="mb-3">
            <label for="shop-filter-search" class="form-label">{{ __('shop.listing.search') }}</label>
            <input id="shop-filter-search" type="search" name="q" class="form-control"
                value="{{ $filters['q'] ?? '' }}">
        </div>

        @if ($category)
            <nav class="mb-3" aria-label="{{ __('shop.listing.category_navigation') }}">
                <h3 class="h6">{{ __('shop.listing.category') }}</h3>
                <ul class="list-unstyled mb-0">
                    @foreach ($categoryBreadcrumbs as $breadcrumbCategory)
                        @php($breadcrumbTranslation = $breadcrumbCategory->translations->first())
                        <li class="mb-1">
                            <a href="{{ route('shop.categories.show', ['slug' => $breadcrumbTranslation->slug]) }}"
                                class="{{ $breadcrumbCategory->is($category) ? 'fw-semibold' : '' }}"
                                @if ($breadcrumbCategory->is($category)) aria-current="page" @endif>
                                {{ $breadcrumbTranslation->name }}
                            </a>
                        </li>
                    @endforeach
                    @foreach ($category->children as $childCategory)
                        @php($childTranslation = $childCategory->translations->first())
                        <li class="ms-3 mb-1">
                            <a href="{{ route('shop.categories.show', ['slug' => $childTranslation->slug]) }}">{{ $childTranslation->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <fieldset class="mb-3">
            <legend class="form-label fs-6">{{ __('shop.listing.price') }}</legend>
            <div class="row g-2">
                <div class="col-6">
                    <label for="shop-filter-min-price" class="visually-hidden">{{ __('shop.listing.minimum_price') }}</label>
                    <input id="shop-filter-min-price" type="number" name="min_price" min="0" step="0.0001"
                        class="form-control" value="{{ $filters['min_price'] ?? '' }}"
                        placeholder="{{ __('shop.listing.minimum') }}">
                </div>
                <div class="col-6">
                    <label for="shop-filter-max-price" class="visually-hidden">{{ __('shop.listing.maximum_price') }}</label>
                    <input id="shop-filter-max-price" type="number" name="max_price" min="0" step="0.0001"
                        class="form-control" value="{{ $filters['max_price'] ?? '' }}"
                        placeholder="{{ __('shop.listing.maximum') }}">
                </div>
            </div>
        </fieldset>

        @foreach ([
            'stock' => ['value' => 'in', 'label' => __('shop.listing.in_stock')],
            'sale' => ['value' => '1', 'label' => __('shop.listing.on_sale')],
            'featured' => ['value' => '1', 'label' => __('shop.listing.featured')],
            'new' => ['value' => '1', 'label' => __('shop.listing.new')],
        ] as $name => $option)
            <div class="form-check mb-2">
                <input id="shop-filter-{{ $name }}" class="form-check-input" type="checkbox"
                    name="{{ $name }}" value="{{ $option['value'] }}"
                    @checked(($filters[$name] ?? null) == $option['value'])>
                <label class="form-check-label" for="shop-filter-{{ $name }}">{{ $option['label'] }}</label>
            </div>
        @endforeach

        @if ($attributeFacets !== [])
            <h3 class="h6 mt-3">{{ __('shop.listing.attribute_filters') }}</h3>
        @endif
        @foreach ($attributeFacets as $facet)
            <fieldset class="mt-3" data-category-attribute="{{ $facet['code'] }}">
                <legend class="form-label fs-6">{{ $facet['label'] }}</legend>
                @foreach ($facet['options'] as $option)
                    @php($inputId = 'shop-filter-attribute-'.$facet['code'].'-'.$option['code'])
                    <div class="form-check mb-2">
                        <input id="{{ $inputId }}" class="form-check-input" type="checkbox"
                            name="attributes[{{ $facet['code'] }}][]" value="{{ $option['code'] }}"
                            @checked(in_array($option['code'], $filters['attributes'][$facet['code']] ?? [], true))>
                        <label class="form-check-label" for="{{ $inputId }}">{{ $option['label'] }}</label>
                    </div>
                @endforeach
            </fieldset>
        @endforeach

        <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'newest' }}">
        <div class="d-grid gap-2 mt-4">
            <button type="submit" class="btn btn-primary">{{ __('shop.listing.apply_filters') }}</button>
            <a href="{{ $listingAction }}" class="btn btn-outline-secondary">
                {{ __('shop.listing.clear_filters') }}
            </a>
        </div>
    </form>
</aside>
