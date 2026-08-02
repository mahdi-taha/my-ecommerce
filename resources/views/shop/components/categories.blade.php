<div class="container-fluid py-5 bg-light" data-homepage-categories>
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width:600px;">
            <h1 class="display-6">{{ __('shop.categories.title') }}</h1>
            <p class="text-muted">
                {{ __('shop.categories.subtitle') }}
            </p>
        </div>

        <div class="row g-4">
            @foreach ($homepageCategories as $category)
                @php($translation = $category->translations->first())
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="{{ route('shop.categories.show', ['slug' => $translation->slug]) }}" class="category-card text-center d-block">
                        <div class="category-icon">
                            @if ($category->homepage_logo_url)
                                <img src="{{ $category->homepage_logo_url }}" alt="{{ $translation->name }}">
                            @else
                                <i class="fas fa-th-large fa-3x text-muted" aria-hidden="true"></i>
                            @endif
                        </div>

                        <h5 class="mt-3 mb-1">{{ $translation->name }}</h5>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>
