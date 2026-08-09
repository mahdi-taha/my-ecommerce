<x-admin-main page="Edit Customer">
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
                    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                        <h3 class="mb-0">Edit Customer</h3>
                        <div class="d-flex gap-2">
                            @if ($customer->has_account)
                                <a href="{{ route('admin.customers.password.edit', $customer) }}"
                                    class="btn btn-outline-primary">Change Password</a>
                            @endif
                            <a href="{{ route('admin.customers.index') }}" class="btn btn-transparent">Back</a>
                        </div>
                    </div>

                    <div class="card shadow">
                        <div class="card-body">
                            <form action="{{ route('admin.customers.update', $customer) }}" method="POST" onsubmit="disableSubmitButton(this)">
                                @csrf
                                @method('PUT')
                                @include('admin.customers._form')
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
