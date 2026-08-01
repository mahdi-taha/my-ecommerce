<div class="col-lg-3 d-none d-lg-block">
    <nav class="navbar navbar-light position-relative" style="width: 250px;">
        <button class="navbar-toggler border-0 fs-4 w-100 px-0 text-start"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#allCat"
                aria-label="{{ __('shop.navigation.toggle_categories') }}">

            <h4 class="m-0">
                <i class="fa fa-bars me-2"></i>
                {{ __('shop.navigation.all_categories') }}
            </h4>

        </button>

        <div class="collapse navbar-collapse rounded-bottom" id="allCat">
            <div class="navbar-nav ms-auto py-0">

                @include('shop.components.category-tree', ['categories' => $storefrontCategoryTree])

            </div>
        </div>
    </nav>
</div>
