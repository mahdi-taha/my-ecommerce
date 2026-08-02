document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('cmsPagesTable')) return;
    $('#cmsPagesTable').DataTable({ processing:true, serverSide:true, responsive:true, order:[[3,'asc']], ajax:window.cmsPageDataTableRoute, columns:[
        {data:'title',name:'title',orderable:false},{data:'code',name:'code'},{data:'is_active',name:'is_active',searchable:false},{data:'sort_order',name:'sort_order'},{data:'updated_at',name:'updated_at'},{data:'action',name:'action',orderable:false,searchable:false}
    ]});
});
