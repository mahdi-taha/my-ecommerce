<x-admin-main page="Orders">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/orders.js'])
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
                            <h3>Orders</h3>

                            <div class="row mt-3">
                                <div class="col-lg-3 col-md-6 mb-2">
                                    <label for="order-search" class="form-label">Search</label>
                                    <input type="search" id="order-search" class="form-control"
                                        placeholder="Order number, customer, or email">
                                </div>
                                <div class="col-lg-3 col-md-6 mb-2">
                                    <label for="order-status-filter" class="form-label">Order Status</label>
                                    <select id="order-status-filter" class="form-select">
                                        <option value="">All Order Statuses</option>
                                        <option value="pending">Pending</option>
                                        <option value="processing">Processing</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-2">
                                    <label for="payment-status-filter" class="form-label">Payment Status</label>
                                    <select id="payment-status-filter" class="form-select">
                                        <option value="">All Payment Statuses</option>
                                        <option value="pending">Pending</option>
                                        <option value="paid">Paid</option>
                                        <option value="failed">Failed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-2">
                                    <label for="fulfillment-status-filter" class="form-label">Fulfillment Status</label>
                                    <select id="fulfillment-status-filter" class="form-select">
                                        <option value="">All Fulfillment Statuses</option>
                                        <option value="unfulfilled">Unfulfilled</option>
                                        <option value="out_for_delivery">Out for Delivery</option>
                                        <option value="fulfilled">Fulfilled</option>
                                        <option value="delivery_failed">Delivery Failed</option>
                                    </select>
                                </div>
                                <div class="col-lg-4 col-md-6 mb-2">
                                    <label for="customer-filter" class="form-label">Customer</label>
                                    <input type="search" id="customer-filter" class="form-control"
                                        placeholder="Customer name or email">
                                </div>
                                <div class="col-lg-3 col-md-6 mb-2">
                                    <label for="date-from-filter" class="form-label">Date From</label>
                                    <input type="date" id="date-from-filter" class="form-control">
                                </div>
                                <div class="col-lg-3 col-md-6 mb-2">
                                    <label for="date-to-filter" class="form-label">Date To</label>
                                    <input type="date" id="date-to-filter" class="form-control">
                                </div>
                                <div class="col-lg-2 col-md-6 mb-2 d-flex align-items-end">
                                    <button type="button" id="clear-order-filters" class="btn btn-outline-secondary w-100">
                                        Clear Filters
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="ordersTable" class="display table data-table mt-3 mb-3" style="width: 100%">
                                    <thead>
                                        <tr>
                                            <th>Order Number</th>
                                            <th>Customer</th>
                                            <th>Order Date</th>
                                            <th>Item Count</th>
                                            <th>Grand Total</th>
                                            <th>Order Status</th>
                                            <th>Payment Status</th>
                                            <th>Fulfillment Status</th>
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
        window.orderDataTableRoute = "{{ route('admin.orders.index') }}";
    </script>
</x-admin-main>
