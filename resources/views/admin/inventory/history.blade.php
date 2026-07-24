<x-admin-main page="Inventory History">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/inventory.js'])
    </x-slot>
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full" data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper"><x-admin-topbar /><div class="body-wrapper-inner"><div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mt-4 mb-3"><h3 class="mb-0">Inventory History</h3><a href="{{ route('admin.inventory.index') }}" class="btn btn-transparent">Back</a></div>
            <div class="card shadow">
                <div class="card-head p-3"><div class="row g-2">
                    <div class="col-md-5"><select id="history-product-filter" class="form-select inventory-product-select" data-placeholder="All Products"><option value="">All Products</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->sku }} - {{ $product->translations->first()?->name ?? $product->configurable?->translations->first()?->name ?? 'Product' }}</option>@endforeach</select></div>
                    <div class="col-md-3"><select id="history-type-filter" class="form-select"><option value="">All Movement Types</option>@foreach (\App\Models\InventoryMovement::types() as $type)<option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>@endforeach</select></div>
                </div></div>
                <div class="card-body"><div class="table-responsive"><table id="inventoryHistoryTable" class="display table data-table w-100"><thead><tr><th>Date</th><th>Product</th><th>SKU</th><th>Type</th><th>Reference</th><th>Change</th><th>Before</th><th>After</th><th>Unit Cost</th><th>Total Cost</th><th>Notes</th><th>Created By</th></tr></thead></table></div></div>
            </div>
        </div></div></div>
    </div>
    <script>window.inventoryHistoryRoute = @json(route('admin.inventory.history'));</script>
</x-admin-main>
