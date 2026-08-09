<x-admin-main page="Create Refund">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/refunds.js'])
    </x-slot>
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                        <h3 class="mb-0">Create Refund</h3>
                        <a href="{{ route('admin.refunds.index') }}" class="btn btn-transparent">Back</a>
                    </div>
                    <hr>
                    @if (!$order)
                        <section class="card card-body" data-refund-order-lookup
                            data-lookup-url="{{ route('admin.refunds.lookups.orders') }}">
                            <label class="form-label" for="refund-order-search">Find an Order</label>
                            <input class="form-control" id="refund-order-search" type="search"
                                autocomplete="off" placeholder="Search by Order Number, customer name, or email"
                                aria-describedby="refund-order-search-help refund-order-search-status">
                            <div id="refund-order-search-help" class="form-text">
                                Search by Order Number, customer name, or email.
                            </div>
                            <div id="refund-order-search-status" class="small text-muted mt-3" role="status"
                                aria-live="polite"></div>
                            <div class="list-group mt-2" data-refund-order-results></div>
                        </section>
                    @else
                        <form method="POST" action="{{ route('admin.refunds.store') }}" class="card card-body" onsubmit="disableSubmitButton(this)">
                            @csrf
                            <input type="hidden" name="order_id" value="{{ $order->id }}"><input type="hidden"
                                name="idempotency_key" value="{{ $idempotencyKey }}">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <h5 class="mb-0">{{ $order->order_number }} — {{ $order->customer_email }}</h5>
                                <a href="{{ route('admin.refunds.create') }}" class="btn btn-sm btn-outline-secondary">Change Order</a>
                            </div>
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Select</th>
                                            <th>Item</th>
                                            <th>Remaining</th>
                                            <th>Refund Quantity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($items as $index => $row)
                                            <tr>
                                                <td><input type="hidden" name="items[{{ $index }}][selected]"
                                                        value="0"><input class="form-check-input" type="checkbox"
                                                        name="items[{{ $index }}][selected]" value="1"
                                                        @checked(old("items.$index.selected"))></td>
                                                <td>{{ $row['order_item']->name }}</td>
                                                <td>{{ $row['remaining_quantity'] }}</td>
                                                <td>
                                                    <input type="hidden"
                                                        name="items[{{ $index }}][order_item_id]"
                                                        value="{{ $row['order_item']->id }}">
                                                    <input class="form-control"
                                                        name="items[{{ $index }}][quantity]" inputmode="decimal"
                                                        value="{{ old("items.$index.quantity") }}"
                                                        placeholder="0.0000">
                                                </td>
                                        </tr>@empty<tr>
                                                <td colspan="4">No financially refundable merchandise remains.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <label class="form-label">Return Shipping Cost</label><input class="form-control"
                                name="return_shipping_cost" value="{{ old('return_shipping_cost', '0.0000') }}"
                                required>
                            <label class="form-label mt-3">Shipping Treatment</label><select class="form-select"
                                name="shipping_treatment" required>
                                <option value="company_absorbs">Company absorbs</option>
                                <option value="deduct_from_refund">Deduct from refund</option>
                            </select>
                            <label class="form-label mt-3">Reason</label><input class="form-control" name="reason"
                                maxlength="500" value="{{ old('reason') }}">
                            <label class="form-label mt-3">Customer Note</label>
                            <textarea class="form-control" name="customer_note" maxlength="2000">{{ old('customer_note') }}</textarea>
                            <label class="form-label mt-3">Internal Note</label>
                            <textarea class="form-control" name="internal_note" maxlength="2000">{{ old('internal_note') }}</textarea>
                            <div class="row">
                                <div class="col-12 text-end mt-3">
                                    <button type="submit" class="btn btn-primary shadow">
                                        <span class="btn-text">
                                            <i class="bi bi-floppy me-2"></i>
                                            Save
                                        </span>
                                        <span class="btn-loading d-none">
                                            Saving...
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
