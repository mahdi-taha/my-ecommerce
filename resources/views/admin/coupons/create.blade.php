<x-admin-main page="Create Coupon">
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
                            <h4> <b>Create Coupon</b> </h4>
                        </div>
                        <div class="col-6 text-end">
                            <a href="{{ route('admin.coupons.index') }}" class="btn btn-transparent">Back</a>
                        </div>
                    </div>
                    <hr>
                    <form action="{{ route('admin.coupons.store') }}" method="POST" onsubmit="disableSubmitButton(this)">
                        @csrf
                        @include('admin.coupons._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
