<x-admin-main page="Create Refund">
    <x-slot name="header">@vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])</x-slot>
    <div class="page-wrapper" id="main-wrapper"><x-admin-sidebar /><div class="body-wrapper"><x-admin-topbar />
        <div class="container-fluid py-4"><h3>Create Refund</h3>
            @if(!$order)
                <form method="GET" class="card card-body"><label class="form-label">Order ID</label><input class="form-control" name="order" type="number" required><button class="btn btn-primary mt-3">Load Order</button></form>
            @else
                <form method="POST" action="{{ route('admin.refunds.store') }}" class="card card-body">@csrf
                    <input type="hidden" name="order_id" value="{{ $order->id }}"><input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                    <h5>{{ $order->order_number }} — {{ $order->customer_email }}</h5>
                    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    <div class="table-responsive"><table class="table"><thead><tr><th>Select</th><th>Item</th><th>Remaining</th><th>Refund Quantity</th></tr></thead><tbody>
                    @forelse($items as $index => $row)<tr><td><input type="hidden" name="items[{{ $index }}][selected]" value="0"><input class="form-check-input" type="checkbox" name="items[{{ $index }}][selected]" value="1" @checked(old("items.$index.selected"))></td><td>{{ $row['order_item']->name }}</td><td>{{ $row['remaining_quantity'] }}</td><td>
                        <input type="hidden" name="items[{{ $index }}][order_item_id]" value="{{ $row['order_item']->id }}">
                        <input class="form-control" name="items[{{ $index }}][quantity]" inputmode="decimal" value="{{ old("items.$index.quantity") }}" placeholder="0.0000">
                    </td></tr>@empty<tr><td colspan="4">No financially refundable merchandise remains.</td></tr>@endforelse</tbody></table></div>
                    <label class="form-label">Return Shipping Cost</label><input class="form-control" name="return_shipping_cost" value="{{ old('return_shipping_cost', '0.0000') }}" required>
                    <label class="form-label mt-3">Shipping Treatment</label><select class="form-select" name="shipping_treatment" required><option value="company_absorbs">Company absorbs</option><option value="deduct_from_refund">Deduct from refund</option></select>
                    <label class="form-label mt-3">Reason</label><input class="form-control" name="reason" maxlength="500" value="{{ old('reason') }}">
                    <label class="form-label mt-3">Customer Note</label><textarea class="form-control" name="customer_note" maxlength="2000">{{ old('customer_note') }}</textarea>
                    <label class="form-label mt-3">Internal Note</label><textarea class="form-control" name="internal_note" maxlength="2000">{{ old('internal_note') }}</textarea>
                    <button class="btn btn-danger mt-3" @disabled($items->isEmpty())>Complete Refund</button>
                </form>
            @endif
        </div></div></div>
</x-admin-main>
