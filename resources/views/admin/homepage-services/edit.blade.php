<x-admin-main page="Edit Homepage Service">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])
    </x-slot>
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6"
        data-sidebartype="full" data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid py-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h4 mb-0">Edit Homepage Service</h1>
                        <a href="{{ route('admin.homepage-services.index') }}" class="btn btn-transparent">Back</a>
                    </div>
                    <form method="POST" action="{{ route('admin.homepage-services.update', $homepageService) }}">
                        @csrf
                        @method('PUT')
                        @include('admin.homepage-services._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
