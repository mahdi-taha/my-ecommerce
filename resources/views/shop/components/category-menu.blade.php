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

        <div class="dropdown-menu storefront-category-mega-menu p-0" id="categoryMegaMenu"
            aria-labelledby="categoryMegaMenuToggle" data-category-mega-menu>
            <div class="row g-0">
                <div class="col-4 storefront-category-roots">
                    @foreach ($storefrontCategoryTree as $category)
                        <button type="button"
                            class="storefront-category-root w-100 border-0 text-start {{ $loop->first ? 'active' : '' }}"
                            id="category-root-{{ $category->id }}" data-category-root
                            data-category-panel="category-panel-{{ $category->id }}"
                            aria-controls="category-panel-{{ $category->id }}"
                            aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                            {{ $category->translations->first()->name }}
                        </button>
                    @endforeach
                </div>

                <div class="col-8 storefront-category-panels">
                    @foreach ($storefrontCategoryTree as $category)
                        <section id="category-panel-{{ $category->id }}" class="storefront-category-panel"
                            aria-labelledby="category-root-{{ $category->id }}" data-category-panel-content
                            @if (! $loop->first) hidden @endif>
                            <a class="h5 d-inline-block text-dark text-decoration-none mb-3" href="#">
                                {{ $category->translations->first()->name }}
                            </a>

                            @if ($category->children->isNotEmpty())
                                <div class="row row-cols-1 row-cols-lg-2 row-cols-xl-3 g-4">
                                    @foreach ($category->children as $child)
                                        <div class="col">
                                            <a class="fw-semibold text-dark text-decoration-none d-block mb-2" href="#">
                                                {{ $child->translations->first()->name }}
                                            </a>
                                            @if ($child->children->isNotEmpty())
                                                @include('shop.components.category-mega-branch', [
                                                    'categories' => $child->children,
                                                ])
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    @endforeach
                </div>
            </div>
        </div>
    </nav>
</div>
