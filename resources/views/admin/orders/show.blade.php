<x-admin-main page="Order Details">
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
                    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                        <div>
                            <h3 class="mb-1">Order {{ $order->order_number }}</h3>
                            <div class="d-flex flex-wrap gap-2">
                                {!! $badges['order'] !!}
                                {!! $badges['payment'] !!}
                                {!! $badges['fulfillment'] !!}
                            </div>
                        </div>
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-transparent">Back to Orders</a>
                    </div>

                    @if ($order->status === \App\Enums\OrderStatus::Cancelled->value
                        && $order->payment_status === \App\Enums\PaymentStatus::Paid->value)
                        <div class="alert alert-warning" role="alert">
                            <strong>Paid order requires refund.</strong>
                            Refund processing is not implemented yet.
                        </div>
                    @endif

                    @if (in_array(true, $availableActions, true))
                        <div class="card shadow mb-4">
                            <div class="card-header"><h5 class="mb-0">Actions</h5></div>
                            <div class="card-body d-flex flex-wrap gap-2">
                                @if ($availableActions['process'])
                                    @if ($availableActions['process_blocked'])
                                        <div>
                                            <button type="button" class="btn btn-primary" disabled>Process Order</button>
                                            <small class="text-danger d-block mt-1">
                                                Payment is required before this order can be processed.
                                            </small>
                                        </div>
                                    @else
                                        <form action="{{ route('admin.orders.process', $order) }}" method="POST"
                                            class="order-lifecycle-form" data-confirm-title="Process this order?"
                                            data-confirm-text="Inventory will be deducted when this order moves to processing."
                                            data-confirm-button="Process Order">
                                            @csrf
                                            <button type="submit" class="btn btn-primary">Process Order</button>
                                        </form>
                                    @endif
                                @endif

                                @if ($availableActions['mark_paid'])
                                    <form action="{{ route('admin.orders.payments.paid', $order) }}" method="POST"
                                        class="order-lifecycle-form" data-confirm-title="Mark this payment as paid?"
                                        data-confirm-text="The payment status will be changed to paid."
                                        data-confirm-button="Mark Paid">
                                        @csrf
                                        <button type="submit" class="btn btn-success">Mark Paid</button>
                                    </form>
                                @endif

                                @if ($availableActions['mark_failed'])
                                    <form action="{{ route('admin.orders.payments.failed', $order) }}" method="POST"
                                        class="order-lifecycle-form" data-confirm-title="Mark this payment as failed?"
                                        data-confirm-text="The payment status will be changed to failed."
                                        data-confirm-button="Mark Failed">
                                        @csrf
                                        <button type="submit" class="btn btn-warning">Mark Failed</button>
                                    </form>
                                @endif

                                @if ($availableActions['retry_payment'])
                                    <form action="{{ route('admin.orders.payments.retry', $order) }}" method="POST"
                                        class="order-lifecycle-form" data-confirm-title="Retry this payment?"
                                        data-confirm-text="A new pending payment attempt will be created."
                                        data-confirm-button="Retry Payment">
                                        @csrf
                                        <button type="submit" class="btn btn-primary">Retry Payment</button>
                                    </form>
                                @endif

                                @if ($availableActions['out_for_delivery'])
                                    <form action="{{ route('admin.orders.out-for-delivery', $order) }}" method="POST"
                                        class="order-lifecycle-form" data-confirm-title="Mark this order as out for delivery?"
                                        data-confirm-text="The order will be recorded as dispatched with the driver."
                                        data-confirm-button="Out for Delivery">
                                        @csrf
                                        <button type="submit" class="btn btn-info">Out for Delivery</button>
                                    </form>
                                @endif

                                @if ($availableActions['fulfill'])
                                    <form action="{{ route('admin.orders.fulfill', $order) }}" method="POST"
                                        class="order-lifecycle-form" data-confirm-title="Mark this order as fulfilled?"
                                        data-confirm-text="The fulfillment status will be changed to fulfilled."
                                        data-confirm-button="Fulfill Order">
                                        @csrf
                                        <button type="submit" class="btn btn-success">Fulfill Order</button>
                                    </form>
                                @endif

                                @if ($availableActions['delivery_failed'])
                                    <form action="{{ route('admin.orders.delivery-failed', $order) }}" method="POST"
                                        class="order-lifecycle-form" data-confirm-title="Mark delivery as failed?"
                                        data-confirm-text="The order will be cancelled and its inventory will be restored."
                                        data-confirm-button="Delivery Failed" data-confirm-color="#dc3545">
                                        @csrf
                                        <button type="submit" class="btn btn-danger">Delivery Failed</button>
                                    </form>
                                @endif

                                @if ($availableActions['cancel'])
                                    <form action="{{ route('admin.orders.cancel', $order) }}" method="POST"
                                        class="order-lifecycle-form" data-confirm-title="Cancel this order?"
                                        data-confirm-text="Inventory may be restored when cancelling a processing order."
                                        data-confirm-button="Cancel Order" data-confirm-color="#dc3545">
                                        @csrf
                                        <button type="submit" class="btn btn-danger">Cancel Order</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <span class="text-muted d-block">Created At</span>
                                    <strong>{{ $order->created_at?->format('Y-m-d H:i') ?? '—' }}</strong>
                                </div>
                                <div class="col-md-3">
                                    <span class="text-muted d-block">Completed At</span>
                                    <strong>{{ $order->completed_at ? date('Y-m-d H:i', strtotime($order->completed_at)) : '—' }}</strong>
                                </div>
                                <div class="col-md-3">
                                    <span class="text-muted d-block">Cancelled At</span>
                                    <strong>{{ $order->cancelled_at ? date('Y-m-d H:i', strtotime($order->cancelled_at)) : '—' }}</strong>
                                </div>
                                <div class="col-md-3">
                                    <span class="text-muted d-block">Currency</span>
                                    <strong>{{ $order->currency_code }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-4 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header"><h5 class="mb-0">Customer Information</h5></div>
                                <div class="card-body">
                                    <dl class="row mb-0">
                                        <dt class="col-5">First Name</dt><dd class="col-7">{{ $order->customer_first_name }}</dd>
                                        <dt class="col-5">Last Name</dt><dd class="col-7">{{ $order->customer_last_name }}</dd>
                                        <dt class="col-5">Email</dt><dd class="col-7 text-break">{{ $order->customer_email }}</dd>
                                        <dt class="col-5">Phone</dt><dd class="col-7">{{ $order->customer_phone ?: '—' }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        @foreach (['Billing Address' => $order->billingAddress, 'Shipping Address' => $order->shippingAddress] as $title => $address)
                            <div class="col-lg-4 mb-4">
                                <div class="card shadow h-100">
                                    <div class="card-header"><h5 class="mb-0">{{ $title }}</h5></div>
                                    <div class="card-body">
                                        @if ($address)
                                            <p class="mb-1"><strong>{{ $address->first_name }} {{ $address->last_name }}</strong></p>
                                            @if ($address->company)<p class="mb-1">{{ $address->company }}</p>@endif
                                            <p class="mb-1">{{ $address->address_line_1 }}</p>
                                            @if ($address->address_line_2)<p class="mb-1">{{ $address->address_line_2 }}</p>@endif
                                            <p class="mb-1">
                                                {{ $address->city }}@if ($address->state), {{ $address->state }}@endif
                                                @if ($address->postal_code) {{ $address->postal_code }}@endif
                                            </p>
                                            <p class="mb-1">{{ $address->country_code }}</p>
                                            @if ($address->email)<p class="mb-1 text-break">{{ $address->email }}</p>@endif
                                            @if ($address->phone)<p class="mb-0">{{ $address->phone }}</p>@endif
                                        @else
                                            <p class="text-muted mb-0">No address snapshot available.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header"><h5 class="mb-0">Order Items</h5></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>SKU</th>
                                            <th>Product Name</th>
                                            <th class="text-end">Quantity</th>
                                            <th class="text-end">Unit Price</th>
                                            <th class="text-end">Row Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($rootItems as $item)
                                            <tr>
                                                <td>{{ $item->sku }}</td>
                                                <td>
                                                    <strong>{{ $item->name }}</strong>
                                                    @if ($item->option_summary)
                                                        <small class="text-muted d-block">{{ $item->option_summary }}</small>
                                                    @endif
                                                </td>
                                                <td class="text-end">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}</td>
                                                <td class="text-end">{{ $order->currency_code }} {{ number_format((float) $item->unit_price, 2) }}</td>
                                                <td class="text-end">{{ $order->currency_code }} {{ number_format((float) $item->row_total, 2) }}</td>
                                            </tr>
                                            @foreach ($item->children as $child)
                                                <tr class="table-light">
                                                    <td class="ps-4">{{ $child->sku }}</td>
                                                    <td class="ps-4">
                                                        <span class="me-1">↳</span>{{ $child->name }}
                                                        @if ($child->option_summary)
                                                            <small class="text-muted d-block">{{ $child->option_summary }}</small>
                                                        @endif
                                                    </td>
                                                    <td class="text-end">{{ rtrim(rtrim(number_format((float) $child->quantity, 4, '.', ''), '0'), '.') }}</td>
                                                    <td class="text-end">{{ $order->currency_code }} {{ number_format((float) $child->unit_price, 2) }}</td>
                                                    <td class="text-end">{{ $order->currency_code }} {{ number_format((float) $child->row_total, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        @empty
                                            <tr><td colspan="5" class="text-center text-muted">No order items.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-7 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header"><h5 class="mb-0">Payments</h5></div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table align-middle mb-0">
                                            <thead><tr><th>Method</th><th>Status</th><th>Amount</th><th>Transaction Reference</th><th>Paid At</th><th>Failed At</th></tr></thead>
                                            <tbody>
                                                @forelse ($order->payments as $payment)
                                                    <tr>
                                                        <td>{{ ucwords(str_replace('_', ' ', $payment->method)) }}</td>
                                                        <td><span class="badge {{ $paymentBadgeClasses[$payment->status] ?? 'bg-secondary' }}">{{ ucfirst($payment->status) }}</span></td>
                                                        <td>{{ $order->currency_code }} {{ number_format((float) $payment->amount, 2) }}</td>
                                                        <td>{{ $payment->transaction_reference ?: '—' }}</td>
                                                        <td>{{ $payment->paid_at ? date('Y-m-d H:i', strtotime($payment->paid_at)) : '—' }}</td>
                                                        <td>{{ $payment->failed_at ? date('Y-m-d H:i', strtotime($payment->failed_at)) : '—' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="6" class="text-center text-muted">No payment attempts.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header"><h5 class="mb-0">Order Totals</h5></div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span>{{ $order->currency_code }} {{ number_format((float) $order->subtotal, 2) }}</span></div>
                                    <div class="d-flex justify-content-between mb-2"><span>Discount</span><span>{{ $order->currency_code }} {{ number_format((float) $order->discount_total, 2) }}</span></div>
                                    <div class="d-flex justify-content-between mb-2"><span>Shipping</span><span>{{ $order->currency_code }} {{ number_format((float) $order->shipping_total, 2) }}</span></div>
                                    <div class="d-flex justify-content-between mb-2"><span>Tax</span><span>{{ $order->currency_code }} {{ number_format((float) $order->tax_total, 2) }}</span></div>
                                    <hr>
                                    <div class="d-flex justify-content-between fs-5 fw-bold"><span>Grand Total</span><span>{{ $order->currency_code }} {{ number_format((float) $order->grand_total, 2) }}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header"><h5 class="mb-0">Status History</h5></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead><tr><th>Date</th><th>Type</th><th>From</th><th>To</th><th>User</th><th>Comment</th></tr></thead>
                                    <tbody>
                                        @forelse ($order->statusHistory as $history)
                                            <tr>
                                                <td>{{ $history->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                                <td>{{ ucfirst($history->type) }}</td>
                                                <td>{{ $history->from_status ? ucwords(str_replace('_', ' ', $history->from_status)) : '—' }}</td>
                                                <td>{{ ucwords(str_replace('_', ' ', $history->to_status)) }}</td>
                                                <td>{{ $history->user?->name ?? 'System / Deleted User' }}</td>
                                                <td>{{ $history->comment ?: '—' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center text-muted">No status history.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
