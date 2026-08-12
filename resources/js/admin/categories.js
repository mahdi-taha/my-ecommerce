document.addEventListener('DOMContentLoaded', function () {

    if (document.querySelector('body[data-page="Categories"]')) {
        if ($.fn.DataTable.isDataTable('#categoriesTable')) {
            return;
        }

        const categoriesTable = $('#categoriesTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            order: [[1, 'asc']],

            ajax: {
                url: window.dataTablesRoutes.categories,
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
                    data: 'name',
                    name: 'name'
                },
                {
                    data: 'parent_name',
                    name: 'parent_name'
                },
                {
                    data: 'level',
                    name: 'level'
                },
                {
                    data: 'position',
                    name: 'position'
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

        $('#categoriesTable').on('click', '.category-delete', async function () {
            const button = this;
            const confirmation = await window.Swal.fire({
                title: 'Delete category?',
                text: `The category "${button.dataset.name}" will be permanently deleted.`,
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
                categoriesTable.ajax.reload(null, false);
            } catch (error) {
                await window.Swal.fire({
                    icon: 'error',
                    title: 'Unable to delete category',
                    text: error.errors?.category?.[0]
                        ?? error.message
                        ?? 'The category could not be deleted.'
                });
                button.disabled = false;
            }
        });
    }

    if (
        document.querySelector('body[data-page="Create Category"]') ||
        document.querySelector('body[data-page="Edit Category"]')
    ) {
        const isEditPage = Boolean(
            document.querySelector('body[data-page="Edit Category"]')
        );
        const categoryForm = document.querySelector('.body-wrapper-inner form');
        const parentSelect = $('.category-parent-select');
        let invalidFieldHandled = false;

        parentSelect.select2({
            placeholder: parentSelect.data('placeholder'),
            allowClear: true,
            width: '100%'
        });

        const parentSelection = parentSelect
            .next('.select2-container')
            .find('.select2-selection');

        const parentSelectWrapper = parentSelect.closest('.select2-div');

        // if (parentSelect.data('has-error') === true) {
        //     parentSelectWrapper.addClass('select2-danger');
        // }

        // parentSelect.on('change', function () {
        //     parentSelectWrapper.removeClass('select2-danger');
        // });

        categoryForm?.addEventListener('invalid', function (event) {
            if (invalidFieldHandled) {
                return;
            }

            invalidFieldHandled = true;

            const invalidField = event.target;
            const inactiveTabPane = invalidField.closest('.tab-pane:not(.active)');

            if (inactiveTabPane) {
                const tabButton = document.querySelector(
                    `[data-bs-target="#${inactiveTabPane.id}"]`
                );

                tabButton?.click();
            }

            requestAnimationFrame(function () {
                invalidField.focus();
            });

            setTimeout(function () {
                invalidFieldHandled = false;
            }, 0);
        }, true);

        function slugify(value) {
            return value
                .normalize('NFKD')
                .replace(/\p{M}/gu, '')
                .toLowerCase()
                .trim()
                .replace(/[^\p{L}\p{N}]+/gu, '-')
                .replace(/^-+|-+$/g, '');
        }

        function safeUploadIdentifier() {
            if (typeof window.crypto?.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }

            if (typeof window.crypto?.getRandomValues === 'function') {
                const values = new Uint32Array(4);
                window.crypto.getRandomValues(values);

                return Array.from(values, function (value) {
                    return value.toString(16).padStart(8, '0');
                }).join('-');
            }

            return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
        }

        function normalizeUploadFilename(input, file) {
            if (typeof window.DataTransfer !== 'function') {
                console.warn('Category upload filename normalization is unavailable: DataTransfer is not supported.');

                return file;
            }

            const originalExtension = file.name.includes('.')
                ? file.name.split('.').pop().toLowerCase()
                : '';
            const safeExtension = originalExtension.replace(/[^a-z0-9]/g, '');
            const safeFilename = `${safeUploadIdentifier()}${safeExtension ? `.${safeExtension}` : ''}`;

            try {
                const normalizedFile = new File([file], safeFilename, {
                    type: file.type,
                    lastModified: file.lastModified
                });
                const transfer = new DataTransfer();
                transfer.items.add(normalizedFile);
                input.files = transfer.files;

                return normalizedFile;
            } catch (error) {
                console.warn('Category upload filename normalization could not be applied.', error);

                return file;
            }
        }

        document.querySelectorAll('.category-name').forEach(function (nameInput) {
            const locale = nameInput.dataset.locale;
            const slugInput = document.querySelector(
                `.category-slug[data-locale="${locale}"]`
            );

            if (!slugInput) {
                return;
            }

            let slugWasEdited = isEditPage
                ? false
                : slugInput.value !== '' &&
                    slugInput.value !== slugify(nameInput.value);

            nameInput.addEventListener('input', function () {
                if (!slugWasEdited) {
                    slugInput.value = slugify(nameInput.value);
                }
            });

            slugInput.addEventListener('input', function () {
                slugWasEdited = true;
            });
        });

        document.querySelectorAll('.category-image-input').forEach(function (input) {
            input.addEventListener('change', function () {
                const preview = document.getElementById(input.dataset.preview);
                const removeButton = document.getElementById(input.dataset.remove);
                const selectedFile = input.files?.[0];

                if (!preview || !removeButton || !selectedFile) {
                    return;
                }

                const file = normalizeUploadFilename(input, selectedFile);

                preview.src = URL.createObjectURL(file);
                preview.classList.remove('d-none');
                removeButton.classList.remove('d-none');
            });
        });

        document.querySelectorAll('.category-image-remove').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = document.getElementById(button.dataset.input);
                const preview = document.getElementById(button.dataset.preview);

                if (!input || !preview) {
                    return;
                }

                input.value = '';
                const existingSource = preview.dataset.existingSrc;

                if (existingSource) {
                    preview.src = existingSource;
                    preview.classList.remove('d-none');
                } else {
                    preview.removeAttribute('src');
                    preview.classList.add('d-none');
                }

                button.classList.add('d-none');
            });
        });
    }
});
