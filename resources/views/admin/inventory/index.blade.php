<x-admin-main page="Inventory">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/inventory.js'])
    </x-slot>

    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 mb-3">
                        <h3 class="mb-0">Inventory</h3>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('admin.inventory.opening') }}" class="btn btn-outline-primary">Opening Stock</a>
                            <a href="{{ route('admin.inventory.receive') }}" class="btn btn-primary">Receive Stock</a>
                            <a href="{{ route('admin.inventory.adjustment') }}" class="btn btn-outline-primary">Adjustment</a>
                            <a href="{{ route('admin.inventory.stock-count') }}" class="btn btn-outline-primary">Stock Count</a>
                            <a href="{{ route('admin.inventory.history') }}" class="btn btn-outline-secondary">History</a>
                        </div>
                    </div>

                    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

                    <div class="card shadow">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="inventoryTable" class="display table data-table w-100">
                                    <thead><tr><th>Name</th><th>SKU</th><th>Type</th><th>On Hand</th><th>Available</th><th>Average Cost</th><th>Low Stock Alert</th><th>Actions</th></tr></thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>window.inventoryDataTableRoute = @json(route('admin.inventory.index'));</script>
</x-admin-main>
