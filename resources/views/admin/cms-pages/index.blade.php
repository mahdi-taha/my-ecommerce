<x-admin-main page="CMS Pages">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/cms-pages.js'])
    </x-slot>
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="card shadow mt-4">
                        <div class="card-header">
                            <h1 class="h4 mb-0">CMS Pages</h1>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="cmsPagesTable" class="display table data-table" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Code</th>
                                            <th>Status</th>
                                            <th>Order</th>
                                            <th>Updated</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    <script>
        window.cmsPageDataTableRoute = @json(route('admin.cms-pages.index'));
    </script>
</x-admin-main>
