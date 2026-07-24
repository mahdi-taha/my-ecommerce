document.addEventListener('DOMContentLoaded', function () {
    function formatInventoryNumber(value, emptyValue = '0') {
        if (value === null || value === undefined || value === '' || value === '-') {
            return emptyValue;
        }

        const number = Number.parseFloat(value);

        return Number.isNaN(number)
            ? emptyValue
            : number.toFixed(4).replace(/\.?0+$/, '');
    }

    if (document.querySelector('body[data-page="Inventory"]')) {
        $('#inventoryTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: window.inventoryDataTableRoute,
            order: [[1, 'asc']],
            columns: [
                { data: 'name', name: 'name', orderable: false },
                { data: 'sku', name: 'sku' },
                { data: 'product_type', name: 'product_type', searchable: false, orderable: false },
                { data: 'quantity', name: 'quantity', searchable: false, orderable: false, render: (data) => formatInventoryNumber(data) },
                {
                    data: 'available_quantity', name: 'available_quantity', searchable: false, orderable: false,
                    render: function (data, type, row) {
                        const formatted = formatInventoryNumber(data);
                        const available = Number.parseFloat(data);
                        const threshold = Number.parseFloat(row.low_stock_alert);

                        if (type === 'display' && !Number.isNaN(available) && !Number.isNaN(threshold) && available <= threshold) {
                            return `<span class="text-danger fw-bold">${formatted}</span>`;
                        }

                        return formatted;
                    }
                },
                { data: 'average_cost', name: 'average_cost', searchable: false, orderable: false, render: (data) => formatInventoryNumber(data) },
                { data: 'low_stock_alert', name: 'low_stock_alert', searchable: false, orderable: false, render: (data) => formatInventoryNumber(data, '-') },
                { data: 'action', name: 'action', searchable: false, orderable: false }
            ]
        });
    }

    if (document.querySelector('body[data-page="Inventory History"]')) {
        const table = $('#inventoryHistoryTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: window.inventoryHistoryRoute,
                data: function (data) {
                    data.product_id = document.getElementById('history-product-filter').value;
                    data.type = document.getElementById('history-type-filter').value;
                }
            },
            order: [[0, 'desc']],
            columns: [
                { data: 'created_at', name: 'created_at' },
                { data: 'product_name', name: 'product_name', orderable: false },
                { data: 'sku', name: 'sku', orderable: false },
                { data: 'type', name: 'type' },

                // NEW
                { data: 'reference', name: 'reference', orderable: false, searchable: false },

                { data: 'quantity', name: 'quantity', render: (data) => formatInventoryNumber(data) },
                { data: 'quantity_before', name: 'quantity_before', render: (data) => formatInventoryNumber(data, '-') },
                { data: 'quantity_after', name: 'quantity_after', render: (data) => formatInventoryNumber(data, '-') },
                { data: 'unit_cost', name: 'unit_cost', render: (data) => formatInventoryNumber(data, '-') },
                { data: 'total_cost', name: 'total_cost', render: (data) => formatInventoryNumber(data, '-') },
                { data: 'notes', name: 'notes', orderable: false },
                { data: 'created_by_name', name: 'created_by_name', orderable: false }
            ]
        });

        $('#history-product-filter, #history-type-filter').on('change', function () {
            table.ajax.reload();
        });
    }

    $('.inventory-product-select').each(function () {
        const select = $(this);
        select.select2({
            placeholder: select.data('placeholder') || 'Select Product',
            allowClear: true,
            width: '100%'
        });
    });

    const productSelect = document.getElementById('product_id');
    const currentQuantity = document.getElementById('current_quantity');
    const availableQuantity = document.getElementById('available_quantity');
    const currentAverageCost = document.getElementById('current_average_cost');
    const currentLowStockAlert = document.getElementById('current_low_stock_alert');
    const countedQuantity = document.getElementById('counted_quantity');
    const difference = document.getElementById('stock_count_difference');

    function updateProductContext() {
        const option = productSelect?.selectedOptions[0];
        if (currentQuantity) currentQuantity.value = option?.value ? formatInventoryNumber(option.dataset.quantity) : '';
        if (availableQuantity) availableQuantity.value = option?.value ? formatInventoryNumber(option.dataset.availableQuantity) : '';
        if (currentAverageCost) currentAverageCost.value = option?.value ? formatInventoryNumber(option.dataset.averageCost) : '';
        if (currentLowStockAlert) currentLowStockAlert.value = option?.value ? formatInventoryNumber(option.dataset.lowStockAlert, '-') : '';
        updateCountDifference();
    }

    function updateCountDifference() {
        if (!difference) return;
        const current = Number.parseFloat(currentQuantity?.value) || 0;
        const counted = Number.parseFloat(countedQuantity?.value) || 0;
        difference.value = formatInventoryNumber(counted - current);
    }

    if (productSelect) {
        $(productSelect).on('change', updateProductContext);
    }
    countedQuantity?.addEventListener('input', updateCountDifference);
    updateProductContext();
});
