<x-admin-main page="Create Product">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/products.js'])
    </x-slot>

    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />

        <div class="body-wrapper">
            <x-admin-topbar />

            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-6">
                            <h4><b>Add Product</b></h4>
                        </div>
                        <div class="col-6 text-end">
                            <a href="{{ route('admin.products.index') }}" class="btn btn-transparent">Back</a>
                        </div>
                    </div>

                    <hr>

                    @include('admin.products._form', ['isEdit' => false])
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
