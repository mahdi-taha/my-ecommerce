<x-admin-main page="Edit Homepage Content">
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
                    <div class="row align-items-center mb-4">
                        <div class="col-8">
                            <h4 class="mb-0"><b>Edit Homepage Content</b></h4>
                        </div>
                        <div class="col-4 text-end">
                            <a href="{{ route('admin.homepage-banners.index') }}" class="btn btn-transparent">Back</a>
                        </div>
                    </div>
                    <hr>
                    <form method="POST" enctype="multipart/form-data"
                        action="{{ route('admin.homepage-banners.update', $homepageBanner) }}"
                        onsubmit="disableSubmitButton(this)">
                        @csrf
                        @method('PUT')
                        @include('admin.homepage-banners._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
