<div class="col-lg-3 d-none d-lg-block storefront-category-menu">
    <nav class="navbar navbar-light position-relative dropdown" style="width: 250px;">
        <button class="navbar-toggler border-0 fs-4 w-100 px-0 text-start dropdown-toggle"
            id="categoryMegaMenuToggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
            aria-expanded="false" aria-controls="categoryMegaMenu"
            aria-label="{{ __('shop.navigation.toggle_categories') }}">
            <span class="h4 m-0">
                <i class="fa fa-bars me-2" aria-hidden="true"></i>
                {{ __('shop.navigation.all_categories') }}
            </span>
        </button>

        <div class="dropdown-menu storefront-category-panel-menu p-0" id="categoryMegaMenu"
            aria-labelledby="categoryMegaMenuToggle" data-category-navigation-desktop>
            <div class="storefront-category-root-scrollport">
                <ul class="storefront-category-desktop-list list-unstyled mb-0" role="list">
                    @forelse ($storefrontCategoryNavigation as $rootCategory)
                        <li class="storefront-category-menu-item {{ $rootCategory['children'] !== [] ? 'has-children' : '' }}">
                            <a class="dropdown-item storefront-category-link" href="{{ $rootCategory['url'] }}"
                                @if ($rootCategory['children'] !== [])
                                    data-category-flyout-trigger="root-{{ $rootCategory['id'] }}"
                                    aria-controls="category-flyout-root-{{ $rootCategory['id'] }}"
                                    aria-expanded="false"
                                @endif>
                                <span>{{ $rootCategory['name'] }}</span>
                                @if ($rootCategory['children'] !== [])
                                    <i class="fas fa-chevron-right storefront-category-forward-icon" aria-hidden="true"></i>
                                @endif
                            </a>
                        </li>
                    @empty
                        <li class="px-3 py-2 text-muted small">{{ __('shop.navigation.no_subcategories') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="storefront-category-flyout-layer" data-category-flyout-layer>
                @foreach ($storefrontCategoryNavigation as $rootCategory)
                    @if ($rootCategory['children'] !== [])
                        <div class="storefront-category-flyout storefront-category-flyout--level-2"
                            id="category-flyout-root-{{ $rootCategory['id'] }}"
                            data-category-flyout="root-{{ $rootCategory['id'] }}">
                            <div class="storefront-category-level-2-scrollport">
                                <ul class="storefront-category-desktop-list list-unstyled mb-0" role="list">
                                    @foreach ($rootCategory['children'] as $childCategory)
                                        <li class="storefront-category-menu-item {{ $childCategory['children'] !== [] ? 'has-children' : '' }}">
                                            <a class="dropdown-item storefront-category-link" href="{{ $childCategory['url'] }}"
                                                @if ($childCategory['children'] !== [])
                                                    data-category-flyout-trigger="child-{{ $childCategory['id'] }}"
                                                    aria-controls="category-flyout-child-{{ $childCategory['id'] }}"
                                                    aria-expanded="false"
                                                @endif>
                                                <span>{{ $childCategory['name'] }}</span>
                                                @if ($childCategory['children'] !== [])
                                                    <i class="fas fa-chevron-right storefront-category-forward-icon" aria-hidden="true"></i>
                                                @endif
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="storefront-category-flyout-layer storefront-category-flyout-layer--level-3">
                                @foreach ($rootCategory['children'] as $childCategory)
                                    @if ($childCategory['children'] !== [])
                                        <div class="storefront-category-flyout storefront-category-flyout--level-3"
                                            id="category-flyout-child-{{ $childCategory['id'] }}"
                                            data-category-flyout="child-{{ $childCategory['id'] }}">
                                            <ul class="storefront-category-desktop-list list-unstyled mb-0" role="list">
                                                @foreach ($childCategory['children'] as $grandchildCategory)
                                                    <li>
                                                        <a class="dropdown-item storefront-category-link" href="{{ $grandchildCategory['url'] }}">
                                                            {{ $grandchildCategory['name'] }}
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </nav>
</div>
