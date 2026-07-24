
document.addEventListener('DOMContentLoaded', function () {

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    if (document.querySelector('body[data-page="Attributes"]')) {

        if ($.fn.DataTable.isDataTable('.data-table')) {
            return;
        }

        const attributesTable = $('.data-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            order: [[1, 'asc']],

            ajax: {
                url: window.dataTablesRoutes.attributes,
                error: function (xhr) {
                    console.error('DataTable AJAX error:', xhr.responseText);
                }
            },

            columns: [
                {
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'code',
                    name: 'code'
                },
                {
                    data: 'admin_name',
                    name: 'admin_name',
                    orderable: false
                },
                {
                    data: 'type',
                    name: 'type'
                },
                {
                    data: 'is_required',
                    name: 'is_required'
                },
                {
                    data: 'is_configurable',
                    name: 'is_configurable'
                },
                {
                    data: 'is_filterable',
                    name: 'is_filterable'
                },
                {
                    data: 'is_active',
                    name: 'is_active'
                },
                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false
                }
            ]
        });

        $('.data-table').on('click', '.attribute-delete', async function () {
            const button = this;
            const confirmation = await Swal.fire({
                title: 'Delete attribute?',
                text: `The attribute "${button.dataset.code}" will be permanently deleted.`,
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

                await Swal.fire({
                    toast: true,
                    icon: 'success',
                    title: data.message,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1500
                });
                attributesTable.ajax.reload(null, false);
            } catch (error) {
                const message = error.errors?.attribute?.[0]
                    ?? error.message
                    ?? 'The attribute could not be deleted.';

                await Swal.fire({
                    icon: 'error',
                    title: 'Unable to delete attribute',
                    text: message
                });
                button.disabled = false;
            }
        });
    };

    if (document.querySelector('body[data-page="Create Attribute"]') || document.querySelector('body[data-page="Edit Attribute"]')) {
        const typeSelect = document.getElementById('attribute_type');
        const configurableCheckbox = document.getElementById('is_configurable');
        const configurableLabel = document.getElementById('is_configurable_label');
        const swatchType = document.getElementById('attribute_swatch_type');

        function toggleFields() {

            if (typeSelect.value === 'select') {
                configurableCheckbox.disabled = false;
                configurableLabel.classList.remove('text-muted');
            } else {
                configurableCheckbox.disabled = true;
                configurableCheckbox.checked = false;
                configurableLabel.classList.add('text-muted');
            }

            if (typeSelect.value === 'text') {
                swatchType.value = '';
                swatchType.disabled = true;
            } else {
                swatchType.disabled = false;
            }
        }

        toggleFields();
        typeSelect.addEventListener('change', toggleFields);
    };
    //------------------------------------------Attribute Options----------------------------------------------------//
    if (document.querySelector('body[data-page="Attribute Options"]')) {
        const form = document.getElementById('attribute-options-form');

        if (!form) {
            return;
        }

        const optionsContainer = document.getElementById('options-container');
        const optionTemplate = document.getElementById('option-row-template');
        const swatchTypeSelect = document.getElementById('swatch_type');
        const addOptionButton = document.getElementById('add-option');
        const addOptionBottomButton = document.getElementById('add-option-bottom');
        const emptyOptions = document.getElementById('empty-options');
        const saveButton = document.getElementById('save-options');

        const deletedOptionIds = [];

        function isColorSwatch() {
            return swatchTypeSelect.value === 'color';
        }

        function toggleColorFields() {
            const colorColumns = document.querySelectorAll('.color-column');

            colorColumns.forEach(function (column) {
                column.classList.toggle('d-none', !isColorSwatch());
            });
        }

        function updateEmptyState() {
            const rows = optionsContainer.querySelectorAll('.option-row');

            if (emptyOptions) {
                emptyOptions.classList.toggle('d-none', rows.length > 0);
            }
        }

        function getNextSortOrder() {
            const rows = optionsContainer.querySelectorAll('.option-row');

            if (rows.length === 0) {
                return 0;
            }

            let highestOrder = 0;

            rows.forEach(function (row) {
                const sortOrderInput = row.querySelector('.option-sort-order');
                const value = Number.parseInt(sortOrderInput.value, 10);

                if (!Number.isNaN(value) && value > highestOrder) {
                    highestOrder = value;
                }
            });

            return highestOrder + 1;
        }

        function addOptionRow() {
            const fragment = optionTemplate.content.cloneNode(true);
            const row = fragment.querySelector('.option-row');

            row.querySelector('.option-sort-order').value = getNextSortOrder();

            optionsContainer.appendChild(fragment);

            updateEmptyState();
            toggleColorFields();

            const englishInput = row.querySelector('.option-label-en');

            if (englishInput) {
                englishInput.focus();
            }
        }

        function removeOptionRow(row) {
            const optionId = row.querySelector('.option-id')?.value;

            if (optionId) {
                deletedOptionIds.push(Number(optionId));
            }

            row.remove();

            updateEmptyState();
        }

        function synchronizeColorInputs(row, source) {
            const colorPicker = row.querySelector('.option-color');
            const colorText = row.querySelector('.option-color-text');

            if (!colorPicker || !colorText) {
                return;
            }

            if (source === 'picker') {
                colorText.value = colorPicker.value.toUpperCase();
                return;
            }

            const value = colorText.value.trim();

            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                colorPicker.value = value;
                colorText.value = value.toUpperCase();
            }
        }

        function clearValidationErrors() {
            form.querySelectorAll('.is-invalid').forEach(function (element) {
                element.classList.remove('is-invalid');
            });

            form.querySelectorAll('.invalid-feedback').forEach(function (element) {
                element.textContent = '';
            });
        }

        function collectOptions() {
            const options = [];

            optionsContainer.querySelectorAll('.option-row').forEach(function (row) {
                const optionId = row.querySelector('.option-id')?.value;
                const code = row.querySelector('.option-code')?.value.trim().toLowerCase();
                const labelEn = row.querySelector('.option-label-en')?.value.trim();
                const labelAr = row.querySelector('.option-label-ar')?.value.trim();
                const sortOrder = row.querySelector('.option-sort-order')?.value;
                const colorValue = row.querySelector('.option-color-text')?.value.trim();

                const option = {
                    id: optionId ? Number(optionId) : null,
                    label_en: labelEn,
                    label_ar: labelAr,
                    sort_order: sortOrder === '' ? 0 : Number(sortOrder),
                    swatch_value: isColorSwatch() ? colorValue : null,
                };

                if (optionId && code !== undefined) {
                    option.code = code;
                }

                options.push(option);
            });

            return options;
        }

        // function validateOptions(options) {
        //     let isValid = true;

        //     clearValidationErrors();

        //     const rows = optionsContainer.querySelectorAll('.option-row');

        //     options.forEach(function (option, index) {
        //         const row = rows[index];

        //         const englishInput = row.querySelector('.option-label-en');
        //         const arabicInput = row.querySelector('.option-label-ar');
        //         const colorInput = row.querySelector('.option-color-text');
        //         const sortOrderInput = row.querySelector('.option-sort-order');

        //         if (!option.label_en) {
        //             englishInput.classList.add('is-invalid');

        //             row.querySelector('.option-label-en-error').textContent =
        //                 'English label is required.';

        //             isValid = false;
        //         }

        //         if (!option.label_ar) {
        //             arabicInput.classList.add('is-invalid');

        //             row.querySelector('.option-label-ar-error').textContent =
        //                 'Arabic label is required.';

        //             isValid = false;
        //         }

        //         if (
        //             option.sort_order === null ||
        //             option.sort_order === '' ||
        //             Number.isNaN(Number(option.sort_order)) ||
        //             Number(option.sort_order) < 0
        //         ) {
        //             sortOrderInput.classList.add('is-invalid');

        //             isValid = false;
        //         }

        //         if (
        //             isColorSwatch() &&
        //             !/^#[0-9A-Fa-f]{6}$/.test(option.swatch_value ?? '')
        //         ) {
        //             colorInput.classList.add('is-invalid');
        //             isValid = false;
        //         }
        //     });

        //     return isValid;
        // }
        function validateOptions(options) {
            let isValid = true;

            clearValidationErrors();

            const rows = optionsContainer.querySelectorAll('.option-row');

            options.forEach(function (option, index) {
                const row = rows[index];

                const englishInput = row.querySelector('.option-label-en');
                const codeInput = row.querySelector('.option-code');
                const arabicInput = row.querySelector('.option-label-ar');
                const colorInput = row.querySelector('.option-color-text');
                const sortOrderInput = row.querySelector('.option-sort-order');

                if (codeInput && !option.code) {
                    codeInput.classList.add('is-invalid');
                    row.querySelector('.option-code-error').textContent = 'The option code is required.';
                    isValid = false;
                }

                if (!option.label_en) {
                    englishInput.classList.add('is-invalid');

                    row.querySelector('.option-label-en-error').textContent =
                        'English label is required.';

                    isValid = false;
                }

                if (!option.label_ar) {
                    arabicInput.classList.add('is-invalid');

                    row.querySelector('.option-label-ar-error').textContent =
                        'Arabic label is required.';

                    isValid = false;
                }

                if (
                    option.sort_order === null ||
                    option.sort_order === '' ||
                    Number.isNaN(Number(option.sort_order)) ||
                    Number(option.sort_order) < 0
                ) {
                    sortOrderInput.classList.add('is-invalid');

                    row.querySelector('.option-sort-order-error').textContent =
                        'Order must be 0 or greater.';

                    isValid = false;
                }

                if (
                    isColorSwatch() &&
                    !/^#[0-9A-Fa-f]{6}$/.test(option.swatch_value ?? '')
                ) {
                    colorInput.classList.add('is-invalid');
                    isValid = false;
                }
            });

            return isValid;
        }

        function setSavingState(isSaving) {
            const buttonText = saveButton.querySelector('.btn-text');
            const buttonLoading = saveButton.querySelector('.btn-loading');

            saveButton.disabled = isSaving;

            buttonText?.classList.toggle('d-none', isSaving);
            buttonLoading?.classList.toggle('d-none', !isSaving);
        }

        addOptionButton?.addEventListener('click', addOptionRow);
        addOptionBottomButton?.addEventListener('click', addOptionRow);

        swatchTypeSelect.addEventListener('change', toggleColorFields);

        optionsContainer.addEventListener('click', function (event) {
            const removeButton = event.target.closest('.remove-option');

            if (!removeButton) {
                return;
            }

            const row = removeButton.closest('.option-row');

            if (!row) {
                return;
            }

            removeOptionRow(row);
        });

        optionsContainer.addEventListener('input', function (event) {
            const row = event.target.closest('.option-row');

            if (!row) {
                return;
            }

            if (event.target.classList.contains('option-color')) {
                synchronizeColorInputs(row, 'picker');
            }

            if (event.target.classList.contains('option-color-text')) {
                synchronizeColorInputs(row, 'text');
            }
        });
        function displayServerValidationErrors(errors) {
            clearValidationErrors();

            const rows = optionsContainer.querySelectorAll('.option-row');

            Object.entries(errors).forEach(function ([key, messages]) {
                const message = Array.isArray(messages)
                    ? messages[0]
                    : messages;

                // Matches:
                // options.0.label_en
                // options.1.label_ar
                // options.2.sort_order
                // options.3.swatch_value
                const match = key.match(/^options\.(\d+)\.(.+)$/);

                if (!match) {
                    return;
                }

                const rowIndex = Number(match[1]);
                const fieldName = match[2];
                const row = rows[rowIndex];

                if (!row) {
                    return;
                }

                const fieldMap = {
                    code: {
                        input: '.option-code',
                        feedback: '.option-code-error',
                    },
                    label_en: {
                        input: '.option-label-en',
                        feedback: '.option-label-en-error',
                    },

                    label_ar: {
                        input: '.option-label-ar',
                        feedback: '.option-label-ar-error',
                    },

                    sort_order: {
                        input: '.option-sort-order',
                        feedback: '.option-sort-order-error',
                    },

                    swatch_value: {
                        input: '.option-color-text',
                        feedback: '.option-color-error',
                    },
                };

                const field = fieldMap[fieldName];

                if (!field) {
                    return;
                }

                const input = row.querySelector(field.input);
                const feedback = row.querySelector(field.feedback);

                input?.classList.add('is-invalid');

                if (feedback) {
                    feedback.textContent = message;
                }
            });
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const options = collectOptions();

            if (!validateOptions(options)) {
                return;
            }

            setSavingState(true);

            try {
                const response = await fetch(form.dataset.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': form.querySelector(
                            'input[name="_token"]'
                        ).value,
                    },
                    body: JSON.stringify({
                        swatch_type: swatchTypeSelect.value,
                        options: options,
                        deleted_options: deletedOptionIds,
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    throw data;
                }

                await Swal.fire({
                    toast: true,
                    icon: 'success',
                    title: data.message ?? 'Options saved successfully.',
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1500,
                    timerProgressBar: true,
                });

                window.location.reload();
            } catch (error) {
                if (error.errors) {
                    displayServerValidationErrors(error.errors);

                    Swal.fire({
                        toast: true,
                        icon: 'error',
                        title: 'Please correct the highlighted fields.',
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3500,
                        timerProgressBar: true,
                    });

                    return;
                }

                Swal.fire({
                    toast: true,
                    icon: 'error',
                    title:
                        error.message ??
                        'Something went wrong while saving the options.',
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 5500,
                    timerProgressBar: true,
                });
            } finally {
                setSavingState(false);
            }
        });
        toggleColorFields();
        updateEmptyState();

    }
});
