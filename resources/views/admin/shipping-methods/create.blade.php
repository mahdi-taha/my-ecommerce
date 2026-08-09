<x-admin-main page="Create Shipping Method">
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
                    <div class="row">
                        <div class="col-6">
                            <h4 class="mb-4"><b>Create Shipping Method</b></h4>
                        </div>
                        <div class="col-6 text-end">
                            <a href="{{ route('admin.shipping-methods.index') }}" class="btn btn-transparent">Back</a>
                        </div>
                    </div>
                    <hr />
                    <form action="{{ route('admin.shipping-methods.store') }}" method="POST"
                        onsubmit="disableSubmitButton(this)">
                        @csrf
                        @include('admin.shipping-methods._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
