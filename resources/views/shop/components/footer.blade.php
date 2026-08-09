<footer class="bg-dark text-white py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4 storefront-footer-contact">
                <h2 class="h5">{{ setting('store.store_name',config('app.name')) }}</h2>
                @if(setting('store.store_address'))
                    <p>{{ setting('store.store_address') }}</p>
                @endif
                @if(setting('store.store_phone'))
                    <a class="text-white d-block" href="tel:{{ setting('store.store_phone') }}">
                        {{ setting('store.store_phone') }}
                    </a>
                @endif
                @if(setting('store.store_email'))
                    <a class="text-white d-block" href="mailto:{{ setting('store.store_email') }}">
                        {{ setting('store.store_email') }}
                    </a>
                @endif
            </div>
            <nav class="col-md-4 storefront-footer-navigation" aria-label="{{ __('shop.cms.footer_navigation') }}">
                <h2 class="h5">{{ __('shop.cms.quick_links') }}</h2>
                <ul class="list-unstyled">
                    <li><a class="text-white" href="{{ route('shop.home') }}">{{ __('shop.navigation.home') }}</a></li>
                    <li><a class="text-white" href="{{ route('shop.products.index') }}">{{ __('shop.navigation.shop') }}</a></li>
                    @foreach($storefrontFooterPages as $page)
                        @php($translation=$page->translations->first())
                        <li><a class="text-white" href="{{ route('shop.pages.show',['slug' => $translation->slug]) }}">{{ $translation->title }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <div class="col-md-4 storefront-footer-social-column">
                @if (collect($storefrontSocialLinks)->contains(fn ($url) => filled($url)))
                    <h2 class="h5">{{ __('shop.cms.follow_us') }}</h2>
                    <div class="d-flex flex-wrap gap-2 storefront-footer-social-links">
                        @if (filled($storefrontSocialLinks['facebook']))
                            <a class="btn btn-outline-light btn-md-square"
                                href="{{ $storefrontSocialLinks['facebook'] }}" target="_blank"
                                rel="noopener noreferrer" aria-label="{{ __('shop.topbar.facebook') }}">
                                <i class="bi bi-facebook" aria-hidden="true"></i>
                            </a>
                        @endif
                        @if (filled($storefrontSocialLinks['instagram']))
                            <a class="btn btn-outline-light btn-md-square"
                                href="{{ $storefrontSocialLinks['instagram'] }}" target="_blank"
                                rel="noopener noreferrer" aria-label="{{ __('shop.topbar.instagram') }}">
                                <i class="bi bi-instagram" aria-hidden="true"></i>
                            </a>
                        @endif
                        @if (filled($storefrontSocialLinks['whatsapp']))
                            <a class="btn btn-outline-light btn-md-square"
                                href="{{ $storefrontSocialLinks['whatsapp'] }}" target="_blank"
                                rel="noopener noreferrer" aria-label="{{ __('shop.topbar.whatsapp') }}">
                                <i class="bi bi-whatsapp" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <hr>
        <p class="mb-0">&copy; {{ now()->year }} {{ setting('store.store_name',config('app.name')) }}</p>
    </div>
</footer>
