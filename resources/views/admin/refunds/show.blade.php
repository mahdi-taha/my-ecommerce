<x-admin-main page="Refund Details">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])</x-slot>
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <h3 class="mb-0">{{ $refund->refund_number }}</h3>
                        <div>
                            <a href="{{ route('admin.refunds.index') }}" class="btn btn-transparent">Back</a>
                            <a class="btn btn-primary" href="{{ route('admin.refunds.print', $refund) }}"
                                target="_blank" rel="noopener">Print Refund</a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <p><strong>Order:</strong> <a
                                    href="{{ route('admin.orders.show', $refund->order) }}">{{ $refund->order->order_number }}</a>
                            </p>
                            <p><strong>Merchandise:</strong> {{ $refund->currency_code }}
                                {{ $refund->merchandise_amount }}
                            </p>
                            <p><strong>Return shipping:</strong> {{ $refund->return_shipping_cost }}
                                ({{ str_replace('_', ' ', $refund->shipping_treatment->value) }})</p>
                            <p><strong>Shipping deduction:</strong> {{ $refund->shipping_deduction }}</p>
                            <p><strong>Company shipping loss:</strong> {{ $refund->company_shipping_loss }}</p>
                            <p><strong>Customer refund:</strong> {{ $refund->customer_refund_amount }}</p>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($refund->items as $item)
                                        <tr>
                                            <td>{{ $item->orderItem->name }}</td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>{{ $item->line_amount }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($refund->reason)
                                <p><strong>Reason:</strong> {{ $refund->reason }}</p>
                            @endif
                            @if ($refund->customer_note)
                                <p><strong>Customer Note:</strong> {{ $refund->customer_note }}</p>
                            @endif
                            @if ($refund->internal_note)
                                <p><strong>Internal Note:</strong> {{ $refund->internal_note }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
