<x-admin-main page="Homepage Services">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/homepage-services.js'])
    </x-slot>
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6"
        data-sidebartype="full" data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="card shadow mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center gap-3">
                            <div>
                                <h1 class="h4 mb-1">Homepage Services</h1>
                                <span class="text-muted">{{ $activeServiceCount }} / {{ $maximumActiveServices }} active</span>
                            </div>
                            <a class="btn btn-primary" href="{{ route('admin.homepage-services.create') }}">Add Service</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="homepageServicesTable" class="display table data-table" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Icon</th>
                                            <th>Status</th>
                                            <th>Order</th>
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
    <script>window.homepageServiceDataTableRoute = @json(route('admin.homepage-services.index'));</script>
</x-admin-main>
