<x-admin-main page="Refunds">
    <x-slot name="header">@vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])</x-slot>
    <div class="page-wrapper" id="main-wrapper"><x-admin-sidebar /><div class="body-wrapper"><x-admin-topbar />
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between mb-3"><h3>Refunds</h3><a class="btn btn-primary" href="{{ route('admin.refunds.create') }}">Create Refund</a></div>
            <div class="card"><div class="card-body table-responsive"><table class="table">
                <thead><tr><th>Refund</th><th>Order</th><th>Customer Amount</th><th>Created By</th><th>Date</th></tr></thead>
                <tbody>@forelse($refunds as $refund)<tr>
                    <td><a href="{{ route('admin.refunds.show', $refund) }}">{{ $refund->refund_number }}</a></td>
                    <td><a href="{{ route('admin.orders.show', $refund->order) }}">{{ $refund->order->order_number }}</a></td>
                    <td>{{ $refund->currency_code }} {{ number_format((float) $refund->customer_refund_amount, 4) }}</td>
                    <td>{{ $refund->creator?->name ?? 'Deleted administrator' }}</td><td>{{ $refund->refunded_at->format('Y-m-d H:i') }}</td>
                </tr>@empty<tr><td colspan="5">No refunds.</td></tr>@endforelse</tbody>
            </table>{{ $refunds->links() }}</div></div>
        </div></div></div>
</x-admin-main>
