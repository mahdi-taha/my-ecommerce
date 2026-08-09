<x-admin-main page="Product Reviews">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/reviews.js'])
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
                            <h3 class="mb-0">Product Reviews</h3>
                            <div class="row mt-3">
                                <div class="col-lg-5 col-md-7 mb-2">
                                    <label for="review-search" class="form-label">Search</label>
                                    <input type="search" id="review-search" class="form-control"
                                        placeholder="Product, customer, or review content">
                                </div>
                                <div class="col-lg-3 col-md-5 mb-2">
                                    <label for="review-status-filter" class="form-label">Status</label>
                                    <select id="review-status-filter" class="form-select">
                                        <option value="">All Statuses</option>
                                        @foreach (\App\Enums\ProductReviewStatus::cases() as $status)
                                            <option value="{{ $status->value }}">{{ ucfirst($status->value) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="reviewsTable" class="display table data-table mt-3 mb-3" style="width: 100%">
                                    <thead>
                                        <tr>
                                            <th scope="col">Product</th>
                                            <th scope="col">Customer</th>
                                            <th scope="col">Rating</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Date</th>
                                            <th scope="col">Action</th>
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
        window.reviewDataTableRoute = @json(route('admin.reviews.index'));
    </script>
</x-admin-main>
