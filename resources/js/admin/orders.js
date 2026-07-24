document.addEventListener('DOMContentLoaded', function () {
    if (document.querySelector('body[data-page="Orders"]')) {
        const searchInput = document.getElementById('order-search');
        const customerFilter = document.getElementById('customer-filter');
        const filterIds = [
            'order-status-filter',
            'payment-status-filter',
            'fulfillment-status-filter',
            'date-from-filter',
            'date-to-filter'
        ];

        const table = $('#ordersTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        order: [[2, 'desc']],
        dom: 'rtip',
        ajax: {
            url: window.orderDataTableRoute,
            data: function (data) {
                data.order_status = document.getElementById('order-status-filter').value;
                data.payment_status = document.getElementById('payment-status-filter').value;
                data.fulfillment_status = document.getElementById('fulfillment-status-filter').value;
                data.customer = customerFilter.value;
                data.date_from = document.getElementById('date-from-filter').value;
                data.date_to = document.getElementById('date-to-filter').value;
            },
            error: function (xhr) {
                console.error('Orders DataTable AJAX error:', xhr.responseText);
            }
        },
        columns: [
            { data: 'order_number', name: 'order_number' },
            { data: 'customer', name: 'customer', orderable: false },
            { data: 'placed_at', name: 'placed_at' },
            { data: 'items_count', name: 'items_count', searchable: false },
            { data: 'grand_total', name: 'grand_total', searchable: false },
            { data: 'status', name: 'status', searchable: false },
            { data: 'payment_status', name: 'payment_status', searchable: false },
            { data: 'fulfillment_status', name: 'fulfillment_status', searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No Orders'
        }
        });

        let searchTimer;

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                table.search(searchInput.value).draw();
            }, 300);
        });

        customerFilter.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                table.ajax.reload();
            }, 300);
        });

        filterIds.forEach(function (id) {
            document.getElementById(id).addEventListener('change', function () {
                table.ajax.reload();
            });
        });

        document.getElementById('clear-order-filters').addEventListener('click', function () {
            searchInput.value = '';
            customerFilter.value = '';

            filterIds.forEach(function (id) {
                document.getElementById(id).value = '';
            });

            table.search('').ajax.reload();
        });
    }

    if (document.querySelector('body[data-page="Order Details"]')) {
        document.querySelectorAll('.order-lifecycle-form').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                if (form.dataset.submitting === 'true') {
                    return;
                }

                event.preventDefault();

                const submitButton = event.submitter || form.querySelector('button[type="submit"]');
                const title = form.dataset.confirmTitle || 'Confirm action';
                const message = form.dataset.confirmText || 'Are you sure?';
                const confirmButtonText = form.dataset.confirmButton || 'Confirm';
                const confirmColor = form.dataset.confirmColor || '#0d6efd';
                const confirmed = window.Swal
                    ? (await window.Swal.fire({
                        title: title,
                        text: message,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: confirmButtonText,
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: confirmColor,
                        cancelButtonColor: '#6c757d'
                    })).isConfirmed
                    : window.confirm(message);

                if (!confirmed) {
                    return;
                }

                form.dataset.submitting = 'true';

                if (submitButton) {
                    submitButton.disabled = true;
                }

                form.submit();
            });
        });
    }
});
