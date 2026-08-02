<x-admin-main page="Create Order">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/order-create.js'])
    </x-slot>

    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                        <h3 class="mb-0">Create Order</h3>
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-transparent">Back</a>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.orders.store') }}" id="admin-order-form"
                        data-summary-url="{{ route('admin.orders.summary') }}"
                        data-customer-url="{{ route('admin.orders.lookups.customers') }}"
                        data-product-url="{{ route('admin.orders.lookups.products') }}"
                        data-configuration-url="{{ route('admin.orders.lookups.products.configuration', ['product' => '__PRODUCT__']) }}">
                        @csrf
                        <input type="hidden" name="admin_creation_token" value="{{ $creationToken }}">

                        <div class="row g-4">
                            <div class="col-xl-8">
                                <div class="card shadow mb-4">
                                    <div class="card-body">
                                        <h4 class="card-title">Customer and Address</h4>
                                        <div class="mb-3">
                                            <label for="admin-order-customer" class="form-label">Customer</label>
                                            <select id="admin-order-customer" name="customer_id" class="form-select" required></select>
                                            <div class="form-text">Registered and manual active Customers are available.</div>
                                        </div>

                                        <fieldset class="mb-3">
                                            <legend class="form-label">Address Source</legend>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="address_source"
                                                    id="address-source-manual" value="manual" checked>
                                                <label class="form-check-label" for="address-source-manual">One-time address</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="address_source"
                                                    id="address-source-saved" value="saved">
                                                <label class="form-check-label" for="address-source-saved">Saved address</label>
                                            </div>
                                        </fieldset>

                                        <div id="saved-address-section" class="d-none mb-3">
                                            <label for="saved-address-id" class="form-label">Saved Address</label>
                                            <select id="saved-address-id" name="saved_address_id" class="form-select" disabled></select>
                                        </div>

                                        <div id="manual-address-section" class="row g-3">
                                            @foreach ([
                                                'first_name' => 'First Name', 'last_name' => 'Last Name',
                                                'company' => 'Company', 'email' => 'Email', 'phone' => 'Phone',
                                                'address_line_1' => 'Address Line 1', 'address_line_2' => 'Address Line 2',
                                                'city' => 'City', 'state' => 'State', 'postal_code' => 'Postal Code',
                                                'country_code' => 'Country Code',
                                            ] as $field => $label)
                                                <div class="{{ in_array($field, ['address_line_1', 'address_line_2']) ? 'col-12' : 'col-md-6' }}">
                                                    <label for="manual-address-{{ $field }}" class="form-label">{{ $label }}</label>
                                                    <input id="manual-address-{{ $field }}" name="manual_address[{{ $field }}]"
                                                        class="form-control" value="{{ old('manual_address.'.$field) }}"
                                                        @if (in_array($field, ['first_name', 'last_name', 'address_line_1', 'city', 'country_code'])) required @endif>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div class="card shadow mb-4">
                                    <div class="card-body">
                                        <h4 class="card-title">Products</h4>
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-9">
                                                <label for="admin-order-product" class="form-label">Product</label>
                                                <select id="admin-order-product" class="form-select"></select>
                                            </div>
                                            <div class="col-md-3">
                                                <button type="button" id="add-admin-order-product" class="btn btn-outline-primary w-100">Add Product</button>
                                            </div>
                                        </div>
                                        <div id="configurable-selection" class="border rounded p-3 mt-3 d-none" aria-live="polite"></div>

                                        <div class="table-responsive mt-4">
                                            <table class="table align-middle">
                                                <thead>
                                                    <tr>
                                                        <th scope="col">Product</th>
                                                        <th scope="col">SKU</th>
                                                        <th scope="col" style="width: 140px">Quantity</th>
                                                        <th scope="col">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="admin-order-lines">
                                                    <tr data-empty-row><td colspan="4" class="text-center text-muted">No Products added.</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-4">
                                <div class="card shadow sticky-top" style="top: 90px">
                                    <div class="card-body">
                                        <h4 class="card-title">Order Summary</h4>
                                        <div class="mb-3">
                                            <label for="shipping-method" class="form-label">Shipping Method</label>
                                            <select id="shipping-method" name="shipping_method" class="form-select" required>
                                                @foreach ($shippingMethods as $method)
                                                    <option value="{{ $method->code }}">{{ $method->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="payment-method" class="form-label">Payment Method</label>
                                            <select id="payment-method" name="payment_method" class="form-select" required>
                                                @foreach ($paymentMethods as $method)
                                                    <option value="{{ $method->code }}">{{ $method->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <dl class="row mb-3">
                                            <dt class="col-7">Subtotal</dt><dd class="col-5 text-end" data-summary-subtotal>—</dd>
                                            <dt class="col-7">Discount</dt><dd class="col-5 text-end" data-summary-discount>—</dd>
                                            <dt class="col-7">Tax</dt><dd class="col-5 text-end" data-summary-tax>—</dd>
                                            <dt class="col-7">Shipping</dt><dd class="col-5 text-end" data-summary-shipping>—</dd>
                                            <dt class="col-7 fs-5">Grand Total</dt><dd class="col-5 text-end fs-5 fw-bold" data-summary-grand>—</dd>
                                        </dl>
                                        <div class="alert alert-danger d-none" role="alert" data-summary-error></div>
                                        <button type="submit" class="btn btn-primary w-100" disabled data-create-order>Create Order</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
