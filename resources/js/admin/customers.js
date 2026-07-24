document.addEventListener('DOMContentLoaded', function () {
    const tableElement = document.getElementById('customersTable');
    let table = null;

    if (tableElement) {
        const searchInput = document.getElementById('customer-search');
        const statusFilter = document.getElementById('customer-status-filter');
        const verificationFilter = document.getElementById('customer-verification-filter');

        table = $('#customersTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            order: [[6, 'desc']],
            dom: 'rtip',
            ajax: {
                url: window.customerDataTableRoute,
                data: function (data) {
                    data.status = statusFilter.value;
                    data.verification = verificationFilter.value;
                },
                error: function (xhr) {
                    console.error('Customers DataTable AJAX error:', xhr.responseText);
                }
            },
            columns: [
                { data: 'name', name: 'name' },
                { data: 'email', name: 'email' },
                { data: 'phone', name: 'phone' },
                { data: 'completed_orders_count', name: 'completed_orders_count', searchable: false },
                { data: 'total_spent', name: 'total_spent', searchable: false },
                { data: 'is_active', name: 'is_active', searchable: false },
                { data: 'created_at', name: 'created_at', searchable: false },
                { data: 'actions', name: 'actions', searchable: false, orderable: false }
            ],
            language: {
                emptyTable: 'No Customers'
            }
        });

        let searchTimer;

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                table.search(searchInput.value).draw();
            }, 300);
        });

        [statusFilter, verificationFilter].forEach(function (filter) {
            filter.addEventListener('change', function () {
                table.ajax.reload(null, false);
            });
        });

        document.getElementById('clear-customer-filters').addEventListener('click', function () {
            searchInput.value = '';
            statusFilter.value = '';
            verificationFilter.value = '';
            table.search('').ajax.reload();
        });
    }

    document.addEventListener('click', async function (event) {
        const button = event.target.closest('.customer-status-toggle');

        if (!button || button.dataset.loading === 'true') {
            return;
        }

        const activate = button.dataset.isActive === '1';
        const action = activate ? 'activate' : 'deactivate';
        const customerName = button.dataset.customerName || 'this customer';
        const confirmed = window.Swal
            ? (await window.Swal.fire({
                title: `${activate ? 'Activate' : 'Deactivate'} Customer?`,
                text: `Are you sure you want to ${action} ${customerName}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: activate ? 'Activate' : 'Deactivate',
                cancelButtonText: 'Cancel',
                confirmButtonColor: activate ? '#198754' : '#dc3545'
            })).isConfirmed
            : window.confirm(`Are you sure you want to ${action} ${customerName}?`);

        if (!confirmed) {
            return;
        }

        button.dataset.loading = 'true';
        button.disabled = true;

        try {
            const response = await fetch(button.dataset.url, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ is_active: activate })
            });
            const data = await response.json().catch(function () {
                return {};
            });

            if (!response.ok) {
                const validationMessage = data.errors
                    ? Object.values(data.errors).flat()[0]
                    : null;
                throw new Error(validationMessage || data.message || 'Customer status could not be updated.');
            }

            if (window.Swal) {
                await window.Swal.fire({
                    icon: 'success',
                    title: data.message || 'Customer status updated successfully.',
                    timer: 1800,
                    showConfirmButton: false
                });
            }

            if (button.dataset.reload === 'true') {
                window.location.reload();
            } else if (table) {
                table.ajax.reload(null, false);
            }
        } catch (error) {
            if (window.Swal) {
                window.Swal.fire({
                    icon: 'error',
                    title: 'Unable to update status',
                    text: error.message
                });
            } else {
                window.alert(error.message);
            }

            button.dataset.loading = 'false';
            button.disabled = false;
        }
    });
});
