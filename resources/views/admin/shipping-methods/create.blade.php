<x-admin-main page="Create Shipping Method">
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
                    <h4 class="mb-4"><b>Create Shipping Method</b></h4>
                    <form action="{{ route('admin.shipping-methods.store') }}" method="POST">
                        @csrf
                        @include('admin.shipping-methods._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
