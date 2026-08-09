<x-admin-main page="Coupons">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])
    </x-slot>

    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="mb-0"><b>Coupons</b></h4>
                                <a href="{{ route('admin.coupons.create') }}" class="btn btn-primary">Add Coupon</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Value</th>
                                            <th>Effective Usage</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($coupons as $coupon)
                                            @php($presentationStatus = $coupon->presentationStatus((int) $coupon->effective_usage_count))
                                            <tr>
                                                <td><code>{{ $coupon->code }}</code></td>
                                                <td>{{ $coupon->name }}</td>
                                                <td>{{ str($coupon->type->value)->title() }}</td>
                                                <td>{{ $coupon->value }}{{ $coupon->type === \App\Enums\CouponType::Percentage ? '%' : '' }}
                                                </td>
                                                <td>{{ $coupon->effective_usage_count }}</td>
                                                <td>
                                                    <span class="badge {{ $presentationStatus->badgeClass() }}">
                                                        {{ $presentationStatus->label() }}
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                                        <a href="{{ route('admin.coupons.edit', $coupon) }}"
                                                            class="btn text-primary"><i class="ti ti-edit fs-6"></i></a>
                                                        @if ($coupon->is_active)
                                                            <form
                                                                action="{{ route('admin.coupons.deactivate', $coupon) }}"
                                                                method="POST">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="btn text-warning"><i
                                                                        class="ti ti-user-off fs-6"></i></button>
                                                            </form>
                                                        @endif
                                                        @if ($coupon->usages_count === 0)
                                                            <form
                                                                action="{{ route('admin.coupons.destroy', $coupon) }}"
                                                                method="POST">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn text-danger"><i
                                                                        class="ti ti-trash fs-6"></i></button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">No Coupons found.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            {{ $coupons->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
