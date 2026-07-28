<x-admin-main page="Shipping Methods">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])
    </x-slot>

    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6"
        data-sidebartype="full" data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><b>Shipping Methods</b></h4>
                        <a href="{{ route('admin.shipping-methods.create') }}" class="btn btn-primary">Add Shipping Method</a>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Sort Order</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($shippingMethods as $shippingMethod)
                                            <tr>
                                                <td><code>{{ $shippingMethod->code }}</code></td>
                                                <td>{{ $shippingMethod->name }}</td>
                                                <td>{{ str($shippingMethod->type->value)->replace('_', ' ')->title() }}</td>
                                                <td>{{ $shippingMethod->amount }}</td>
                                                <td>{{ $shippingMethod->sort_order }}</td>
                                                <td>
                                                    <span class="badge {{ $shippingMethod->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                        {{ $shippingMethod->is_active ? 'Active' : 'Inactive' }}
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-inline-flex gap-2">
                                                        <a href="{{ route('admin.shipping-methods.edit', $shippingMethod) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                                        <form action="{{ route('admin.shipping-methods.status.update', $shippingMethod) }}" method="POST">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="is_active" value="{{ $shippingMethod->is_active ? 0 : 1 }}">
                                                            <button type="submit" class="btn btn-sm {{ $shippingMethod->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                                {{ $shippingMethod->is_active ? 'Deactivate' : 'Activate' }}
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="text-center text-muted py-4">No shipping methods found.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            {{ $shippingMethods->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
