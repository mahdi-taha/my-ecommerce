<x-admin-main page="Customer Details">
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
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4 mb-3">
                        <div>
                            <h3 class="mb-1">{{ $customer->name }}</h3>
                            <div class="d-flex gap-2">
                                <span class="badge {{ $customer->is_active ? 'bg-success' : 'bg-danger' }}">
                                    {{ $customer->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span class="badge {{ $customer->email_verified_at ? 'bg-success' : 'bg-warning text-dark' }}">
                                    {{ $customer->email_verified_at ? 'Verified' : 'Unverified' }}
                                </span>
                                <span class="badge {{ $customer->has_account ? 'bg-primary' : 'bg-secondary' }}">
                                    {{ $customer->has_account ? 'Registered' : 'Manual' }}
                                </span>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button"
                                class="btn {{ $customer->is_active ? 'btn-outline-danger' : 'btn-outline-success' }} customer-status-toggle"
                                data-url="{{ route('admin.customers.status.update', $customer) }}"
                                data-is-active="{{ $customer->is_active ? '0' : '1' }}"
                                data-customer-name="{{ $customer->name }}" data-reload="true">
                                {{ $customer->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                            <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-primary">Edit</a>
                            <a href="{{ route('admin.customers.index') }}" class="btn btn-transparent">Back to Customers</a>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card shadow mb-4">
                                <div class="card-header"><h5 class="mb-0">Customer Information</h5></div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6"><span class="text-muted d-block">Full Name</span><strong class="text-break">{{ $customer->name }}</strong></div>
                                        <div class="col-md-6"><span class="text-muted d-block">Email</span><strong class="text-break">{{ $customer->email ?: '-' }}</strong></div>
                                        <div class="col-md-6"><span class="text-muted d-block">Phone</span><strong class="text-break">{{ $customer->phone ?: '-' }}</strong></div>
                                        <div class="col-md-6"><span class="text-muted d-block">Created At</span><strong>{{ $customer->created_at?->format('Y-m-d H:i:s') }}</strong></div>
                                        <div class="col-md-6"><span class="text-muted d-block">Last Login</span><strong>{{ $customer->last_login_at?->format('Y-m-d H:i:s') ?? 'Never' }}</strong></div>
                                    </div>
                                </div>
                            </div>

                            <div class="card shadow mb-4">
                                <div class="card-header"><h5 class="mb-0">Default Address</h5></div>
                                <div class="card-body">
                                    @if ($customer->defaultAddress)
                                        <address class="mb-0 text-break">
                                            <strong>{{ $customer->defaultAddress->first_name }} {{ $customer->defaultAddress->last_name }}</strong><br>
                                            @if ($customer->defaultAddress->company)
                                                {{ $customer->defaultAddress->company }}<br>
                                            @endif
                                            {{ $customer->defaultAddress->address_line_1 }}<br>
                                            @if ($customer->defaultAddress->address_line_2)
                                                {{ $customer->defaultAddress->address_line_2 }}<br>
                                            @endif
                                            {{ $customer->defaultAddress->city }}
                                            @if ($customer->defaultAddress->state), {{ $customer->defaultAddress->state }}@endif
                                            @if ($customer->defaultAddress->postal_code) {{ $customer->defaultAddress->postal_code }}@endif<br>
                                            {{ $customer->defaultAddress->country_code }}
                                            @if ($customer->defaultAddress->phone)<br>{{ $customer->defaultAddress->phone }}@endif
                                        </address>
                                    @else
                                        <p class="text-muted mb-0">No default address available.</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card shadow mb-4">
                                <div class="card-header"><h5 class="mb-0">Completed Commerce</h5></div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Completed Orders</span>
                                        <strong>{{ (int) $customer->completed_orders_count }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Total Spent</span>
                                        <strong>{{ $currencyCode }} {{ number_format((float) ($customer->total_spent ?? 0), 2) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header"><h5 class="mb-0">Recent Orders</h5></div>
                        <div class="card-body">
                            @if ($recentOrders->isEmpty())
                                <p class="text-muted mb-0">No linked orders found.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Order</th>
                                                <th>Date</th>
                                                <th>Order Status</th>
                                                <th>Payment</th>
                                                <th>Fulfillment</th>
                                                <th class="text-end">Grand Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($recentOrders as $order)
                                                <tr>
                                                    <td><a href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                                                    <td>{{ $order->placed_at ? \Illuminate\Support\Carbon::parse($order->placed_at)->format('Y-m-d H:i:s') : '-' }}</td>
                                                    <td>{!! $orderBadges[$order->id]['order'] !!}</td>
                                                    <td>{!! $orderBadges[$order->id]['payment'] !!}</td>
                                                    <td>{!! $orderBadges[$order->id]['fulfillment'] !!}</td>
                                                    <td class="text-end">{{ $order->currency_code }} {{ number_format((float) $order->grand_total, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
