<x-admin-main page="Products">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/products.js'])
    </x-slot>

    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />

        <div class="body-wrapper">
            <x-admin-topbar />

            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="card shadow mt-4">
                        <div class="card-head pt-4 px-4">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <h3>Products</h3>
                                </div>
                                <div class="col-6 text-end">
                                    <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
                                        Add Product
                                    </a>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-3 mb-2">
                                    <select id="product-type-filter" class="form-select">
                                        <option value="">All Types</option>
                                        <option value="standalone_simple">Standalone Simple</option>
                                        <option value="configurable">Configurable</option>
                                        <option value="variant">Variant</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <select id="product-status-filter" class="form-select">
                                        <option value="">All Statuses</option>
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <select id="product-filter" class="form-select">
                                        <option value="">All Products</option>
                                        <option value="featured">Featured</option>
                                        <option value="new">New</option>
                                        <option value="on_sale">On Sale</option>
                                        <option value="zero_price">Zero Price</option>
                                        <option value="out_of_stock">Out of Stock</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="productsTable" class="display table data-table mt-3 mb-3"
                                    style="width: 100%">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Name</th>
                                            <th>SKU</th>
                                            <th>Type</th>
                                            <th>Parent</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.productDataTableRoute = "{{ route('admin.products.index') }}";
    </script>
</x-admin-main>
