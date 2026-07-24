document.addEventListener('DOMContentLoaded', function () {
    const productType = document.getElementById('type');
    const configurableBasePriceField = document.getElementById('configurable-base-price-field');
    const configurableBasePrice = document.getElementById('price');

    function updateConfigurableBasePrice() {
        if (!productType || !configurableBasePriceField || !configurableBasePrice) {
            return;
        }

        const isConfigurable = productType.value === 'configurable';
        configurableBasePriceField.classList.toggle('d-none', !isConfigurable);
        configurableBasePrice.required = isConfigurable;
    }

    productType?.addEventListener('change', updateConfigurableBasePrice);
    updateConfigurableBasePrice();

    if (document.querySelector('body[data-page="Configure Product"]')) {
        const attributeRows = document.querySelectorAll('.configurable-attribute');
        const combinationCount = document.getElementById('combination-count');
        const limitError = document.getElementById('combination-limit-error');
        const submitButton = document.getElementById('generate-variants-button');

        function updateCombinationCount() {
            let total = 1;
            let selectedAttributeCount = 0;
            let everyAttributeHasOptions = true;

            attributeRows.forEach(function (row) {
                const attributeCheckbox = row.querySelector('.configurable-attribute-checkbox');
                const optionCheckboxes = row.querySelectorAll('.configurable-option-checkbox');
                const isSelected = Boolean(attributeCheckbox?.checked);

                optionCheckboxes.forEach(function (option) {
                    option.disabled = !isSelected;
                });

                if (!isSelected) {
                    return;
                }

                selectedAttributeCount++;
                const selectedOptionCount = Array.from(optionCheckboxes)
                    .filter(function (option) {
                        return option.checked;
                    }).length;

                if (selectedOptionCount === 0) {
                    everyAttributeHasOptions = false;
                } else {
                    total *= selectedOptionCount;
                }
            });

            if (selectedAttributeCount === 0 || !everyAttributeHasOptions) {
                total = 0;
            }

            combinationCount.textContent = total;
            limitError?.classList.toggle('d-none', total <= 200);

            if (submitButton) {
                submitButton.disabled = total === 0 || total > 200;
            }
        }

        attributeRows.forEach(function (row) {
            row.addEventListener('change', updateCombinationCount);
        });

        updateCombinationCount();
    }

    if (document.querySelector('body[data-page="Products"]')) {
        const table = $('#productsTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            order: [[2, 'asc']],

            ajax: {
                url: window.productDataTableRoute,
                data: function (data) {
                    data.type = document.getElementById('product-type-filter').value;
                    data.status = document.getElementById('product-status-filter').value;
                },
                error: function (xhr) {
                    console.error('DataTable AJAX error:', xhr.responseText);
                }
            },

            columns: [
                {
                    data: 'image',
                    name: 'image',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'generated_name',
                    name: 'generated_name',
                    orderable: false
                },
                {
                    data: 'sku',
                    name: 'sku'
                },
                {
                    data: 'type',
                    name: 'type'
                },
                {
                    data: 'parent_name',
                    name: 'parent_name',
                    orderable: false
                },
                {
                    data: 'price',
                    name: 'price'
                },
                {
                    data: 'quantity',
                    name: 'quantity',
                    searchable: false,
                    orderable: false
                },
                {
                    data: 'status',
                    name: 'status',
                    searchable: false
                },
                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false
                }
            ]
        });

        $('#product-type-filter, #product-status-filter').on('change', function () {
            table.ajax.reload();
        });

        $('#productsTable').on('click', '.product-delete', async function () {
            const button = this;
            const confirmation = await window.Swal.fire({
                title: 'Delete product?',
                text: `The product "${button.dataset.sku}" will be permanently deleted.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#dc3545'
            });

            if (!confirmation.isConfirmed) {
                return;
            }

            button.disabled = true;

            try {
                const response = await fetch(button.dataset.url, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                const data = await response.json();

                if (!response.ok) {
                    throw data;
                }

                await window.Swal.fire({
                    toast: true,
                    icon: 'success',
                    title: data.message,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1500
                });
                table.ajax.reload(null, false);
            } catch (error) {
                await window.Swal.fire({
                    icon: 'error',
                    title: 'Unable to delete product',
                    text: error.errors?.product?.[0]
                        ?? error.message
                        ?? 'The product could not be deleted.'
                });
                button.disabled = false;
            }
        });
    }

    if (document.querySelector('body[data-page="Manage Variants"]')) {
        const selectedVariants = new Map();
        const bulkAction = document.getElementById('bulk-variant-action');
        const selectAll = document.getElementById('select-all-variants');
        const bulkIds = document.getElementById('bulk-variant-ids');
        const bulkRows = document.getElementById('bulk-variant-rows');
        const applyAll = document.getElementById('bulk-apply-all');
        const actionInput = document.getElementById('bulk-action-input');
        const offcanvasElement = document.getElementById('bulkVariantOffcanvas');
        const offcanvas = offcanvasElement ? bootstrap.Offcanvas.getOrCreateInstance(offcanvasElement) : null;
        const table = $('#variantsTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            order: [[3, 'asc']],
            ajax: {
                url: window.variantManagement.dataTableRoute,
                data: function (data) {
                    data.status = document.getElementById('variant-status-filter').value;
                }
            },
            columns: [
                { data: 'select', name: 'select', orderable: false, searchable: false },
                { data: 'image', name: 'image', orderable: false, searchable: false },
                { data: 'combination', name: 'combination', orderable: false },
                { data: 'sku', name: 'sku' },
                { data: 'price', name: 'price' },
                { data: 'quantity', name: 'quantity', orderable: false, searchable: false },
                { data: 'status', name: 'status', searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ],
            drawCallback: function () {
                document.querySelectorAll('.variant-checkbox').forEach(function (checkbox) {
                    checkbox.checked = selectedVariants.has(checkbox.value);
                });
                updateBulkSelection();
            }
        });

        function updateBulkSelection() {
            const hasSelection = selectedVariants.size > 0;
            bulkAction.disabled = !hasSelection;
            bulkAction.classList.toggle('d-none', !hasSelection);
            bulkIds.innerHTML = Array.from(selectedVariants.keys())
                .map(function (id) {
                    return `<input type="hidden" name="variant_ids[]" value="${id}">`;
                }).join('');
        }

        $('#variantsTable').on('change', '.variant-checkbox', function () {
            if (this.checked) {
                selectedVariants.set(this.value, JSON.parse(this.dataset.variant));
            } else {
                selectedVariants.delete(this.value);
            }
            updateBulkSelection();
        });

        selectAll?.addEventListener('change', function () {
            document.querySelectorAll('.variant-checkbox').forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
                if (selectAll.checked) {
                    selectedVariants.set(checkbox.value, JSON.parse(checkbox.dataset.variant));
                } else {
                    selectedVariants.delete(checkbox.value);
                }
            });
            updateBulkSelection();
        });

        document.getElementById('variant-status-filter')?.addEventListener('change', function () {
            table.ajax.reload();
        });

        function escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = value ?? '';
            return element.innerHTML;
        }

        function field(type, name, value, extra = '') {
            return `<input type="${type}" name="${name}" value="${escapeHtml(value)}" class="form-control bulk-row-field" ${extra}>`;
        }

        function renderBulkEditor(action) {
            actionInput.value = action;
            document.getElementById('bulkVariantOffcanvasLabel').textContent = `Bulk ${bulkAction.options[bulkAction.selectedIndex].text}`;
            let applyMarkup = '<h6>Apply to All</h6>';
            let rowsMarkup = '';

            if (action === 'sku') {
                applyMarkup += '<div class="input-group"><input class="form-control" id="apply-all-sku"><button type="button" class="btn btn-outline-primary bulk-apply-all" data-fields="sku">Apply</button></div>';
            } else if (action === 'prices') {
                applyMarkup += '<div class="row g-2">' +
                    '<div class="col-6"><label class="form-label">Price</label><input type="number" min="0" step="0.0001" class="form-control" id="apply-all-price"></div>' +
                    '<div class="col-6"><label class="form-label">Special Price</label><input type="number" min="0" step="0.0001" class="form-control" id="apply-all-special_price"></div>' +
                    '<div class="col-6"><label class="form-label">From</label><input type="datetime-local" class="form-control" id="apply-all-special_price_from"></div>' +
                    '<div class="col-6"><label class="form-label">To</label><input type="datetime-local" class="form-control" id="apply-all-special_price_to"></div>' +
                    '<div class="col-12"><button type="button" class="btn btn-outline-primary bulk-apply-all" data-fields="price,special_price,special_price_from,special_price_to">Apply</button></div></div>';
            } else if (action === 'status') {
                applyMarkup += '<div class="input-group"><select class="form-select" id="apply-all-status"><option value="1">Active</option><option value="0">Inactive</option></select><button type="button" class="btn btn-outline-primary bulk-apply-all" data-fields="status">Apply</button></div>';
            } else if (action === 'add_images') {
                applyMarkup += '<div class="input-group"><input type="file" accept="image/jpeg,image/png,image/webp" multiple class="form-control" id="apply-all-images"><button type="button" class="btn btn-outline-primary bulk-apply-all-images">Apply</button></div>';
            }

            selectedVariants.forEach(function (variant, id) {
                let controls = '';
                if (action === 'sku') {
                    controls = field('text', `variants[${id}][sku]`, variant.sku, 'data-field="sku" required');
                } else if (action === 'prices') {
                    controls = `<div class="row g-2"><div class="col-6">${field('number', `variants[${id}][price]`, variant.price, 'min="0" step="0.0001" data-field="price" required')}</div><div class="col-6">${field('number', `variants[${id}][special_price]`, variant.special_price, 'min="0" step="0.0001" data-field="special_price"')}</div><div class="col-6">${field('datetime-local', `variants[${id}][special_price_from]`, variant.special_price_from, 'data-field="special_price_from"')}</div><div class="col-6">${field('datetime-local', `variants[${id}][special_price_to]`, variant.special_price_to, 'data-field="special_price_to"')}</div></div>`;
                } else if (action === 'status') {
                    controls = `<select name="variants[${id}][status]" class="form-select bulk-row-field" data-field="status"><option value="1" ${variant.status ? 'selected' : ''}>Active</option><option value="0" ${variant.status ? '' : 'selected'}>Inactive</option></select>`;
                } else if (action === 'add_images') {
                    controls = `<input type="file" name="variants[${id}][images][]" accept="image/jpeg,image/png,image/webp" multiple class="form-control bulk-row-images">`;
                }
                rowsMarkup += `<div class="border rounded p-3 mb-3" data-variant-id="${id}"><strong>${escapeHtml(variant.combination)}</strong><div class="text-muted small mb-2">${escapeHtml(variant.sku)}</div>${controls}</div>`;
            });

            applyAll.innerHTML = applyMarkup;
            bulkRows.innerHTML = rowsMarkup;
            offcanvas?.show();
        }

        bulkAction?.addEventListener('change', async function () {
            const action = bulkAction.value;
            if (!action) return;
            if (['remove_images', 'remove_variants'].includes(action)) {
                const message = action === 'remove_images' ? 'Remove all images from the selected variants?' : 'Remove the selected unused variants?';
                const confirmed = window.Swal
                    ? (await Swal.fire({ title: 'Confirm action', text: message, icon: 'warning', showCancelButton: true })).isConfirmed
                    : window.confirm(message);
                if (confirmed) {
                    actionInput.value = action;
                    document.getElementById('bulk-variant-form').submit();
                }
                bulkAction.value = '';
                return;
            }
            renderBulkEditor(action);
            bulkAction.value = '';
        });

        applyAll?.addEventListener('click', function (event) {
            const button = event.target.closest('.bulk-apply-all');
            if (button) {
                button.dataset.fields.split(',').forEach(function (fieldName) {
                    const source = document.getElementById(`apply-all-${fieldName}`);
                    bulkRows.querySelectorAll(`[data-field="${fieldName}"]`).forEach(input => { input.value = source.value; });
                });
            }
            if (event.target.closest('.bulk-apply-all-images')) {
                const source = document.getElementById('apply-all-images');
                bulkRows.querySelectorAll('.bulk-row-images').forEach(function (input) {
                    const transfer = new DataTransfer();
                    Array.from(source.files).forEach(file => transfer.items.add(file));
                    input.files = transfer.files;
                });
            }
        });
    }

    if (
        document.querySelector('body[data-page="Create Product"]') ||
        document.querySelector('body[data-page="Edit Product"]') ||
        document.querySelector('body[data-page="Edit Variant"]')
    ) {
        const categorySelect = $('.product-category-select');
        const attributeMultiselects = $('.product-attribute-multiselect');
        const relatedProductSelect = $('.product-related-select');

        categorySelect.select2({
            placeholder: categorySelect.data('placeholder'),
            allowClear: true,
            width: '100%'
        });

        attributeMultiselects.each(function () {
            const select = $(this);

            select.select2({
                placeholder: select.data('placeholder'),
                allowClear: true,
                width: '100%'
            });
        });

        relatedProductSelect.select2({
            placeholder: relatedProductSelect.data('placeholder'),
            allowClear: true,
            width: '100%'
        });

        relatedProductSelect.on('select2:select', function (event) {
            const option = event.params.data.element;

            if (option) {
                $(option).detach();
                $(this).append(option).trigger('change.select2');
            }
        });

        const useDefaultTax = document.getElementById('use_default_tax');
        const productTax = document.getElementById('tax_id');

        function updateProductTaxState() {
            if (productTax) {
                productTax.disabled = Boolean(useDefaultTax?.checked);
            }
        }

        useDefaultTax?.addEventListener('change', updateProductTaxState);
        updateProductTaxState();

        document.querySelectorAll('.product-image-delete').forEach(function (button) {
            button.addEventListener('click', function () {
                const row = button.closest('.product-image-row');
                const deleteInput = row?.querySelector('.product-delete-image');
                const baseInput = row?.querySelector('.product-base-image');

                if (!row || !deleteInput) {
                    return;
                }

                deleteInput.checked = true;

                if (baseInput?.checked) {
                    baseInput.checked = false;
                }

                row.classList.add('d-none');
            });
        });

        const imageInput = document.getElementById('new_images');
        const previewContainer = document.getElementById('new-product-image-previews');
        let selectedImages = [];

        function syncNewImageState() {
            selectedImages.forEach(function (image, index) {
                const sortOrder = document.getElementById(`sort_new_${index}`);
                const baseImage = document.getElementById(`base_new_${index}`);

                image.sortOrder = sortOrder?.value ?? image.sortOrder;
                image.isBase = Boolean(baseImage?.checked);
            });
        }

        function updateImageInput() {
            if (!imageInput) {
                return;
            }

            const transfer = new DataTransfer();
            selectedImages.forEach(function (image) {
                transfer.items.add(image.file);
            });
            imageInput.files = transfer.files;
        }

        function renderImagePreviews() {
            if (!previewContainer) {
                return;
            }

            previewContainer.innerHTML = '';

            selectedImages.forEach(function (image, index) {
                const column = document.createElement('div');
                column.className = 'col-md-4 product-new-image-row';
                column.innerHTML = `
                    <div class="border rounded p-2">
                        <img alt="" class="img-fluid rounded mb-2" style="height: 160px; width: 100%; object-fit: cover;">
                        <div class="form-check mb-2">
                            <input class="form-check-input product-base-image" type="radio" name="base_image"
                                id="base_new_${index}" value="new:${index}" ${image.isBase ? 'checked' : ''}>
                            <label class="form-check-label" for="base_new_${index}">Base Image</label>
                        </div>
                        <label class="form-label" for="sort_new_${index}">Sort Order</label>
                        <input type="number" min="0" class="form-control mb-2" id="sort_new_${index}"
                            name="new_image_sort_orders[${index}]" value="${image.sortOrder}">
                        <button type="button" class="btn btn-outline-danger btn-sm product-new-image-delete"
                            data-index="${index}">Remove</button>
                    </div>
                `;

                column.querySelector('img').src = URL.createObjectURL(image.file);
                previewContainer.appendChild(column);
            });

            previewContainer.querySelectorAll('.product-new-image-delete').forEach(function (button) {
                button.addEventListener('click', function () {
                    syncNewImageState();
                    selectedImages.splice(Number.parseInt(button.dataset.index, 10), 1);
                    updateImageInput();
                    renderImagePreviews();
                });
            });
        }

        imageInput?.addEventListener('change', function () {
            selectedImages = Array.from(imageInput.files ?? []).map(function (file, index) {
                return {
                    file: file,
                    sortOrder: index,
                    isBase: false
                };
            });
            renderImagePreviews();
        });
    }
});
