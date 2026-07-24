<x-admin-main page="Customers">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/customers.js'])
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
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <h3 class="mb-0">Customers</h3>
                                <a href="{{ route('admin.customers.create') }}" class="btn btn-primary">Add Customer</a>
                            </div>

                            <div class="row mt-3">
                                <div class="col-lg-4 col-md-6 mb-2">
                                    <label for="customer-search" class="form-label">Search</label>
                                    <input type="search" id="customer-search" class="form-control"
                                        placeholder="Name, email, or phone">
                                </div>
                                <div class="col-lg-3 col-md-6 mb-2">
                                    <label for="customer-status-filter" class="form-label">Status</label>
                                    <select id="customer-status-filter" class="form-select">
                                        <option value="">All Statuses</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-2">
                                    <label for="customer-verification-filter" class="form-label">Email Verification</label>
                                    <select id="customer-verification-filter" class="form-select">
                                        <option value="">All</option>
                                        <option value="verified">Verified</option>
                                        <option value="unverified">Unverified</option>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6 mb-2 d-flex align-items-end">
                                    <button type="button" id="clear-customer-filters"
                                        class="btn btn-outline-secondary w-100">Clear</button>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="customersTable" class="display table data-table mt-3 mb-3" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Completed Orders</th>
                                            <th>Total Spent</th>
                                            <th>Status</th>
                                            <th>Created At</th>
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
        window.customerDataTableRoute = @json(route('admin.customers.index'));
    </script>
</x-admin-main>
