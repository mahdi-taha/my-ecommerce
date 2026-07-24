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
                const file = input.files?.[0];

                if (!preview || !removeButton || !file) {
                    return;
                }

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
