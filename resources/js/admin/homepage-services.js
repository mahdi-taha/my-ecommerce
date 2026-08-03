document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('homepageServicesTable')) return;

    $('#homepageServicesTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        order: [[3, 'asc']],
        ajax: window.homepageServiceDataTableRoute,
        columns: [
            { data: 'title', name: 'title', orderable: false },
            { data: 'icon', name: 'icon' },
            { data: 'is_active', name: 'is_active', searchable: false },
            { data: 'sort_order', name: 'sort_order' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ],
    });
});
