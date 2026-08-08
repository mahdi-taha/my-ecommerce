<x-admin-main page="Reports"><x-slot name="header">@vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])</x-slot>
<div class="page-wrapper" id="main-wrapper"><x-admin-sidebar /><div class="body-wrapper"><x-admin-topbar /><div class="container-fluid py-4"><h3>Reports</h3><div class="row g-3">
@foreach($reports as $name)<div class="col-md-4"><a class="card card-body text-decoration-none h-100" href="{{ route('admin.reports.show', $name) }}"><h5 class="mb-0">{{ str($name)->headline() }}</h5></a></div>@endforeach
</div></div></div></div></x-admin-main>
