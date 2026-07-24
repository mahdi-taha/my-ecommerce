<x-admin-main page="Manage Variants">
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
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4><b>Manage Variants</b></h4>
                            <p class="text-muted mb-0">
                                {{ $product->translations->firstWhere('locale', 'en')?->name }} — {{ $product->sku }}
                                · {{ $product->variants_count }} variants
                            </p>
                        </div>
                        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-transparent">Back</a>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="card shadow">
                        <div class="card-head pt-4 px-4">
                            <div class="row align-items-center">
                                <div class="col-md-4 mb-2">
                                    <select id="variant-status-filter" class="form-select">
                                        <option value="">All Statuses</option>
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-8 text-end mb-2">
                                    <select id="bulk-variant-action" class="form-select d-inline-block w-auto d-none" disabled>
                                        <option value="">Bulk Action</option>
                                        <option value="sku">SKU</option>
                                        <option value="prices">Prices</option>
                                        <option value="status">Status</option>
                                        <option value="add_images">Add Images</option>
                                        <option value="remove_images">Remove Images</option>
                                        <option value="remove_variants">Remove Variants</option>
                                    </select>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#addVariantModal">Add Variant</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="variantsTable" class="display table data-table w-100">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" class="form-check-input" id="select-all-variants"></th>
                                            <th>Image</th>
                                            <th>Combination</th>
                                            <th>SKU</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                            <th>Action</th>
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

    <div class="modal fade" id="addVariantModal" tabindex="-1" aria-labelledby="addVariantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form action="{{ route('admin.products.variants.store', $product) }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addVariantModalLabel">Add Variant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @foreach ($product->superAttributes as $superAttribute)
                        @php $label = $superAttribute->attribute?->translations->first()?->admin_name ?? $superAttribute->attribute?->code; @endphp
                        <div class="mb-3">
                            <label for="variant_option_{{ $superAttribute->attribute_id }}" class="form-label">{{ $label }} *</label>
                            <select id="variant_option_{{ $superAttribute->attribute_id }}"
                                name="options[{{ $superAttribute->attribute_id }}]" class="form-select" required>
                                <option value="">Select {{ $label }}</option>
                                @foreach ($superAttribute->attribute->options as $option)
                                    <option value="{{ $option->id }}">
                                        {{ $option->translations->firstWhere('locale', 'en')?->label ?? 'Option #'.$option->id }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Variant</button>
                </div>
            </form>
        </div>
    </div>

    <div class="offcanvas offcanvas-end custom-offcanvas-width" tabindex="-1" id="bulkVariantOffcanvas"
        aria-labelledby="bulkVariantOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 id="bulkVariantOffcanvasLabel">Bulk Edit Variants</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form action="{{ route('admin.products.variants.bulk-update', $product) }}" method="POST"
                enctype="multipart/form-data" id="bulk-variant-form">
                @csrf
                @method('PATCH')
                <div id="bulk-variant-ids"></div>
                <input type="hidden" name="action" id="bulk-action-input">
                <div id="bulk-apply-all"></div>
                <hr>
                <div id="bulk-variant-rows"></div>
                <button type="submit" class="btn btn-primary w-100"><i class="ti ti-device-floppy me-1"></i>Save</button>
            </form>
        </div>
    </div>

    <script>
        window.variantManagement = {
            dataTableRoute: @json(route('admin.products.variants.index', $product))
        };
    </script>
</x-admin-main>
