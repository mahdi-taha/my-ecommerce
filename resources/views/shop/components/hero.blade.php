@if ($heroBanners->isNotEmpty() || $heroSideBanners->isNotEmpty())
    <div class="container-fluid carousel bg-light px-0 storefront-home-hero">
        <div class="row g-0 justify-content-end align-items-stretch storefront-home-hero-row">
            @if ($heroBanners->isNotEmpty())
                <div class="col-12 col-lg-7 col-xl-9 storefront-hero-main" style="direction: ltr">
                    <div class="header-carousel owl-carousel bg-light storefront-hero-carousel">
                        @foreach ($heroBanners as $banner)
                            @php($translation = $banner->translations->first())
                            <div class="row g-0 align-items-stretch storefront-hero-slide">
                                <div class="col-12 col-md-6 storefront-hero-media">
                                    <img src="{{ $banner->image_url }}" class="storefront-hero-image"
                                        alt="{{ $translation->image_alt?:$translation->title }}">
                                </div>
                                <div class="col-12 col-md-6 carousel-content p-4 storefront-hero-content">
                                    @if ($translation->eyebrow)
                                        <h4 class="text-uppercase fw-bold mb-4">{{ $translation->eyebrow }}</h4>
                                    @endif
                                    <h1 class="display-3 mb-4">{{ $translation->title }}</h1>
                                    @if ($translation->body)
                                        <p class="text-dark">{{ $translation->body }}</p>
                                    @endif
                                    @if ($translation->button_label && $translation->link_url)
                                        <a class="btn btn-primary rounded-pill py-3 px-5" style="width: fit-content"
                                            href="{{ $translation->link_url }}"
                                            @if (str_starts_with($translation->link_url, 'https://')) target="_blank" rel="noopener noreferrer" @endif>
                                            {{ $translation->button_label }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($side = $heroSideBanners->first())
                @php($translation = $side->translations->first())
                <div class="col-12 col-lg-5 col-xl-3 storefront-hero-side-column">
                    <div class="carousel-header-banner storefront-hero-side {{ $heroBanners->isNotEmpty() ? 'storefront-hero-side--paired' : '' }}">
                        <img src="{{ $side->image_url }}" class="storefront-hero-side-image"
                            alt="{{ $translation->image_alt?:$translation->title }}">
                        <div class="carousel-banner">
                            <div class="carousel-banner-content text-center p-4">
                                <h2 class="text-white">{{ $translation->title }}</h2>
                                @if ($translation->body)
                                    <p class="text-white">{{ $translation->body }}</p>
                                @endif
                            </div>
                            @if ($translation->button_label && $translation->link_url)
                                <a class="btn btn-secondary rounded-pill py-2 px-4"
                                    href="{{ $translation->link_url }}"
                                    @if (str_starts_with($translation->link_url, 'https://')) target="_blank" rel="noopener noreferrer" @endif>
                                    {{ $translation->button_label }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
