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
            <div class="row g-0 storefront-category-browser">
                <section class="col-4 storefront-category-column storefront-category-root-column"
                    aria-labelledby="categoryRootHeading">
                    <h2 class="h6 px-3 pt-3" id="categoryRootHeading">{{ __('shop.navigation.category_root') }}</h2>
                    <div class="storefront-category-list" id="categoryRootPanel" data-category-root-panel></div>
                </section>

                <section class="col-4 storefront-category-column" aria-labelledby="categoryChildrenHeading">
                    <h2 class="h6 px-3 pt-3" id="categoryChildrenHeading">{{ __('shop.navigation.category_children') }}</h2>
                    <div class="storefront-category-list" id="categoryChildrenPanel" data-category-children-panel></div>
                </section>

                <section class="col-4 storefront-category-column storefront-category-detail-column"
                    aria-labelledby="categoryDetailHeading">
                    <div class="d-flex align-items-center gap-2 px-3 pt-3">
                        <button class="btn btn-sm btn-link p-0 text-decoration-none" type="button"
                            data-category-detail-back hidden>
                            <i class="fas fa-chevron-left" aria-hidden="true"></i>
                            {{ __('shop.navigation.back') }}
                        </button>
                        <h2 class="h6 mb-0" id="categoryDetailHeading">{{ __('shop.navigation.category_children') }}</h2>
                    </div>
                    <nav class="px-3 pt-2" aria-label="{{ __('shop.navigation.category_children') }}">
                        <ol class="breadcrumb small mb-2" data-category-detail-breadcrumb></ol>
                    </nav>
                    <div class="storefront-category-list" id="categoryDetailPanel" data-category-detail-panel></div>
                </section>
            </div>
        </div>
    </nav>

    <script type="application/json" data-category-navigation-data>{!! Illuminate\Support\Js::encode($storefrontCategoryNavigation) !!}</script>
    <span class="d-none" data-category-empty-label>{{ __('shop.navigation.no_subcategories') }}</span>
</div>
