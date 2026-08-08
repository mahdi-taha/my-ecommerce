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
            <ul class="storefront-category-desktop-list list-unstyled mb-0" role="list">
                @forelse ($storefrontCategoryNavigation as $rootCategory)
                    <li class="storefront-category-menu-item {{ $rootCategory['children'] !== [] ? 'has-children' : '' }}">
                        <a class="dropdown-item storefront-category-link" href="{{ $rootCategory['url'] }}">
                            <span>{{ $rootCategory['name'] }}</span>
                            @if ($rootCategory['children'] !== [])
                                <i class="fas fa-chevron-right storefront-category-forward-icon" aria-hidden="true"></i>
                            @endif
                        </a>
                        @if ($rootCategory['children'] !== [])
                            <ul class="dropdown-menu storefront-category-submenu list-unstyled" role="list">
                                @foreach ($rootCategory['children'] as $childCategory)
                                    <li class="storefront-category-menu-item {{ $childCategory['children'] !== [] ? 'has-children' : '' }}">
                                        <a class="dropdown-item storefront-category-link" href="{{ $childCategory['url'] }}">
                                            <span>{{ $childCategory['name'] }}</span>
                                            @if ($childCategory['children'] !== [])
                                                <i class="fas fa-chevron-right storefront-category-forward-icon" aria-hidden="true"></i>
                                            @endif
                                        </a>
                                        @if ($childCategory['children'] !== [])
                                            <ul class="dropdown-menu storefront-category-submenu list-unstyled" role="list">
                                                @foreach ($childCategory['children'] as $grandchildCategory)
                                                    <li>
                                                        <a class="dropdown-item storefront-category-link" href="{{ $grandchildCategory['url'] }}">
                                                            {{ $grandchildCategory['name'] }}
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @empty
                    <li class="px-3 py-2 text-muted small">{{ __('shop.navigation.no_subcategories') }}</li>
                @endforelse
            </ul>
        </div>
    </nav>
</div>
