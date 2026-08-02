@if ($offerBanners->isNotEmpty())
    <div class="container-fluid py-5 storefront-offers">
        <div class="container">
            <div class="row g-4">
                @foreach ($offerBanners as $banner)
                    @php($translation = $banner->translations->first())
                    <div class="col-lg-6 d-flex">
                        <a href="{{ $translation->link_url ?: '#' }}"
                            class="border bg-white rounded storefront-offer-card"
                            @if ($translation->link_url && str_starts_with($translation->link_url, 'https://')) target="_blank" rel="noopener noreferrer" @endif>
                            <div class="storefront-offer-content">
                                @if ($translation->eyebrow)
                                    <p class="text-muted mb-3">{{ $translation->eyebrow }}</p>
                                @endif
                                <h3 class="text-primary">{{ $translation->title }}</h3>
                                @if ($translation->body)
                                    <p>{{ $translation->body }}</p>
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
