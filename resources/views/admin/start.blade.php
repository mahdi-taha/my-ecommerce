<x-admin-main page="Attributes">
  <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css',  'resources/css/myStyle.css','resources/js/app.js'])

    <style>

    </style>
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
          <div class="card shadow mt-4">
            <div class="card-head pt-4 px-4">
              <div class="row">
                <div class="col-6">
                </div>
                <div class="col-6 text-end">
                  <a href="" class="btn btn-primary">Add</a>
                </div>
              </div>
            </div>
            <div class="card-body ">
              <div class="table-responsive">
                <table id="attributesTable" class="display table data-table  mt-3 mb-3" style="width: 100%">
                  <thead>
                    <tr>
                      <th>No</th>
                      <th>Code</th>
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
</x-admin-main>
