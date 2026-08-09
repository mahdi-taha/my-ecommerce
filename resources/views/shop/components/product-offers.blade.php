@if ($offerBanners->isNotEmpty())
    @php($hasSingleOffer = $offerBanners->count() === 1)
    <div class="container-fluid py-5 storefront-offers">
        <div class="container">
            <div class="row g-4 storefront-offers-grid {{ $hasSingleOffer ? 'storefront-offers-grid--single' : '' }}">
                @foreach ($offerBanners as $banner)
                    @php($translation = $banner->translations->first())
                    <div class="col-12 {{ $hasSingleOffer ? 'col-lg-10 mx-auto' : 'col-lg-6' }} d-flex storefront-offer-column">
                        <a href="{{ $translation->link_url ?: '#' }}"
                            class="storefront-offer-card"
                            @if ($translation->link_url && str_starts_with($translation->link_url, 'https://')) target="_blank" rel="noopener noreferrer" @endif>
                            <div class="storefront-offer-content">
                                @if ($translation->eyebrow)
                                    <p class="storefront-offer-eyebrow">{{ $translation->eyebrow }}</p>
                                @endif
                                <h3 class="storefront-offer-title">{{ $translation->title }}</h3>
                                @if ($translation->body)
                                    <p class="storefront-offer-body">{{ $translation->body }}</p>
                                @endif
                                @if ($translation->button_label && $translation->link_url)
                                    <span class="storefront-offer-cta">
                                        <span>{{ $translation->button_label }}</span>
                                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    </span>
                                @endif
                            </div>
                            <div class="storefront-offer-media">
                                <img src="{{ $banner->image_url }}" class="storefront-offer-image"
                                    alt="{{ $translation->image_alt ?: $translation->title }}">
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
