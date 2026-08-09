<x-admin-main page="Edit Homepage Service">
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
                            <h4> <b>Edit Homepage Service</b> </h4>
                        </div>
                        <div class="col-6 text-end">
                            <a href="{{ route('admin.homepage-services.index') }}" class="btn btn-transparent">Back</a>
                        </div>
                    </div>
                    <hr>
                    <form method="POST" action="{{ route('admin.homepage-services.update', $homepageService) }}" onsubmit="disableSubmitButton(this)">
                        @csrf
                        @method('PUT')
                        @include('admin.homepage-services._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
