<x-admin-main page="Attributes">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/attributes.js'])
    </x-slot>
    <!--  Body Wrapper -->
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />
        <!--  Main wrapper -->
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    @if (session('success_attribute'))
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            {{ session('success_attribute') }}
                        </div>
                    @endif
                    <div class="card shadow mt-4">
                        <div class="card-head pt-4 px-4">
                            <div class="row">
                                <div class="col-6">
                                    <h3>Attributes</h3>
                                </div>
                                <div class="col-6 text-end">
                                    <a href="{{ route('admin.attributes.create') }}" class="btn btn-primary">Add</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body ">
                            <div class="table-responsive">
                                <table id="attributesTable" class="display table data-table  mt-3 mb-3"
                                    style="width: 100%">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Required</th>
                                            <th>Configurable</th>
                                            <th>Filterable</th>
                                            <th>Status</th>
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
    <script>
        window.dataTablesRoutes = {
            attributes: "{{ route('admin.attributes.index') }}",
            attributesDelete: "{{ route('admin.attributes.destroy', ':id') }}",
        };
    </script>
</x-admin-main>
