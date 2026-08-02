document.addEventListener('DOMContentLoaded', function () {
    const tableElement = document.getElementById('reviewsTable');

    if (!tableElement) {
        return;
    }

    const searchInput = document.getElementById('review-search');
    const statusFilter = document.getElementById('review-status-filter');
    const table = $('#reviewsTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        order: [[4, 'desc']],
        dom: 'rtip',
        ajax: {
            url: window.reviewDataTableRoute,
            data: function (data) {
                data.status = statusFilter.value;
            },
            error: function (xhr) {
                console.error('Reviews DataTable AJAX error:', xhr.responseText);
            }
        },
        columns: [
            { data: 'product', name: 'product', orderable: false },
            { data: 'customer', name: 'customer', orderable: false },
            { data: 'rating', name: 'rating' },
            { data: 'status', name: 'status', searchable: false },
            { data: 'created_at', name: 'created_at', searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        language: { emptyTable: 'No Product Reviews' }
    });

    let searchTimer;
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { table.search(searchInput.value).draw(); }, 300);
    });
    statusFilter.addEventListener('change', function () { table.ajax.reload(null, false); });
});
