<x-admin-main page="Create Customer">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])
    </x-slot>

    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                        <h3 class="mb-0">Create Customer</h3>
                        <a href="{{ route('admin.customers.index') }}" class="btn btn-transparent">Back</a>
                    </div>
                    <hr>
                    <div class="card shadow">
                        <div class="card-body">
                            <form action="{{ route('admin.customers.store') }}" method="POST" autocomplete="off"
                                onsubmit="disableSubmitButton(this)">
                                @csrf
                                @include('admin.customers._form')
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
