@if ($homepageServices->isNotEmpty())
    <section class="container-fluid py-4 storefront-services" aria-label="{{ __('shop.home.services') }}">
        <div class="container">
            <div class="storefront-services-grid services-count-{{ $homepageServices->count() }}">
                @foreach ($homepageServices as $homepageService)
                    @php($translation = $homepageService->translations->first())
                    <article class="storefront-service-card">
                        <i class="{{ $homepageService->icon->cssClass() }} storefront-service-icon text-primary"
                            aria-hidden="true"></i>
                        <div>
                            <h2 class="h6 text-uppercase mb-2">{{ $translation->title }}</h2>
                            <p class="mb-0">{{ $translation->description }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
