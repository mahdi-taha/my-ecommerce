<x-admin-main page="Edit Product">
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
                            <h4><b>Edit Product</b></h4>
                        </div>
                        <div class="col-6 text-end d-flex justify-content-end gap-2">
                            @if ($product->type === 'configurable')
                                <a href="{{ route('admin.products.variants.index', $product) }}" class="btn btn-primary">
                                    Manage Variants
                                </a>
                            @endif
                            <a href="{{ route('admin.products.index') }}" class="btn btn-transparent">Back</a>
                        </div>
                    </div>

                    <hr>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($product->type === 'configurable')
                        @include('admin.products._configurable-parent-form')
                    @else
                        @include('admin.products._form', ['isEdit' => true])
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
