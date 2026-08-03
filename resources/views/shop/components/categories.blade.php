<div class="container-fluid py-5 bg-light" data-homepage-categories>
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width:600px;">
            <h1 class="display-6" id="homepage-categories-title">{{ __('shop.categories.title') }}</h1>
            <p class="text-muted">
                {{ __('shop.categories.subtitle') }}
            </p>
        </div>

        <div
            class="storefront-category-carousel"
            data-homepage-category-carousel
            data-category-count="{{ $homepageCategories->count() }}"
            data-previous-label="{{ __('shop.categories.previous') }}"
            data-next-label="{{ __('shop.categories.next') }}"
            aria-labelledby="homepage-categories-title"
        >
            @foreach ($homepageCategories as $category)
                @php($translation = $category->translations->first())
                <div class="storefront-category-carousel-slide">
                    <a href="{{ route('shop.categories.show', ['slug' => $translation->slug]) }}" class="storefront-category-carousel-item">
                        <span class="storefront-category-carousel-media">
                            @if ($category->homepage_logo_url)
                                <img src="{{ $category->homepage_logo_url }}" alt="{{ $translation->name }}">
                            @else
                                <i class="fas fa-th-large fa-3x text-muted" aria-hidden="true"></i>
                            @endif
                        </span>

                        <span class="storefront-category-carousel-name">{{ $translation->name }}</span>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>
