<x-admin-main page="Attribute Options">

    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/attributes.js'])
    </x-slot>

    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">

        <x-admin-sidebar />

        <div class="body-wrapper">

            <x-admin-topbar />

            <div class="body-wrapper-inner">

                <div class="container-fluid">

                    {{-- Heading --}}
                    <div class="row align-items-center">

                        <div class="col-6">

                            <h4 class="mb-1">
                                <b>Manage Options</b>
                            </h4>

                            <p class="text-muted mb-0">
                                {{ $attribute->translations->firstWhere('locale', 'en')?->admin_name }}
                                <span class="mx-1">•</span>
                                {{ $attribute->code }}
                            </p>

                        </div>

                        <div class="col-6 text-end">

                            <a href="{{ route('admin.attributes.index') }}" class="btn btn-transparent">
                                Back
                            </a>

                        </div>

                    </div>

                    <hr>

                    <form id="attribute-options-form"
                        data-url="{{ route('admin.attribute-options.save', $attribute) }}">

                        @csrf

                        {{-- Swatch setting --}}
                        <div class="inputs-container shadow-sm rounded p-3 options-card">

                            <div class="row align-items-end">

                                <div class="col-lg-5">

                                    <label for="swatch_type" class="form-label">
                                        Swatch Type
                                    </label>

                                    <select class="form-select" id="swatch_type" name="swatch_type">

                                        <option value="dropdown"
                                            {{ $attribute->swatch_type === 'dropdown' ? 'selected' : '' }}>
                                            Dropdown
                                        </option>

                                        <option value="text"
                                            {{ $attribute->swatch_type === 'text' ? 'selected' : '' }}>
                                            Text
                                        </option>

                                        <option value="color"
                                            {{ $attribute->swatch_type === 'color' ? 'selected' : '' }}>
                                            Color
                                        </option>

                                    </select>

                                </div>

                                <div class="col-lg-7 text-lg-end mt-3 mt-lg-0">

                                    <button type="button" class="btn btn-outline-primary" id="add-option">

                                        <i class="bi bi-plus-lg me-1"></i>
                                        Add Option

                                    </button>

                                </div>

                            </div>

                        </div>

                        {{-- Options list --}}
                        <div class="inputs-container shadow-sm rounded mt-3 options-card">

                            <div class="p-3">

                                <h5 class="mb-1">Options</h5>

                                <small class="text-muted">
                                    Add, update, reorder or remove options.
                                </small>

                            </div>

                            {{-- Headings --}}
                            <div class="option-header px-3 py-2 d-none d-lg-block">

                                <div class="row align-items-center fw-semibold text-muted">

                                    <div class="col-lg-2">
                                        Code
                                    </div>

                                    <div class="col-lg-3">
                                        English Label
                                    </div>

                                    <div class="col-lg-3">
                                        Arabic Label
                                    </div>

                                    <div class="col-lg-2 color-column">
                                        Color
                                    </div>

                                    <div class="col-lg-1">
                                        Order
                                    </div>

                                    <div class="col-lg-1 text-end">
                                        Action
                                    </div>

                                </div>

                            </div>

                            <div id="options-container">

                                @foreach ($attribute->options as $option)
                                    @php
                                        $englishLabel = $option->translations->firstWhere('locale', 'en')?->label;

                                        $arabicLabel = $option->translations->firstWhere('locale', 'ar')?->label;
                                    @endphp

                                    @php
                                        $codeIsLocked = $option->product_values_exists
                                            || $option->product_super_attributes_exists;
                                    @endphp

                                    <div class="option-row px-3 py-2">

                                        <input type="hidden" class="option-id" value="{{ $option->id }}">

                                        <div class="row align-items-center g-2">

                                            <div class="col-lg-2">
                                                <label class="form-label d-lg-none">Code</label>
                                                <input type="text" class="form-control option-code"
                                                    value="{{ $option->code }}" @readonly($codeIsLocked)
                                                    aria-describedby="option-code-help-{{ $option->id }}">
                                                <div class="invalid-feedback option-code-error"></div>
                                                <div class="form-text" id="option-code-help-{{ $option->id }}">
                                                    @if ($codeIsLocked)
                                                        <i class="ti ti-lock me-1"></i>Locked because this option is in use.
                                                    @else
                                                        Internal identifier. Editable until first use.
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- English --}}
                                            <div class="col-lg-3">

                                                <label class="form-label d-lg-none">
                                                    English Label
                                                </label>

                                                <input type="text" class="form-control option-label-en"
                                                    value="{{ $englishLabel }}" placeholder="Enter English label">

                                                <div class="invalid-feedback option-label-en-error"></div>

                                            </div>

                                            {{-- Arabic --}}
                                            <div class="col-lg-3">

                                                <label class="form-label d-lg-none">
                                                    Arabic Label
                                                </label>

                                                <input type="text" class="form-control option-label-ar"
                                                    value="{{ $arabicLabel }}" placeholder="Enter Arabic label"
                                                    dir="rtl">

                                                <div class="invalid-feedback option-label-ar-error"></div>

                                            </div>

                                            {{-- Color --}}
                                            <div class="col-lg-2 color-column">

                                                <label class="form-label d-lg-none">
                                                    Color
                                                </label>

                                                <div class="d-flex align-items-center gap-2">

                                                    <input type="color"
                                                        class="form-control form-control-color option-color"
                                                        value="{{ $option->swatch_value ?: '#000000' }}">

                                                    <input type="text" class="form-control option-color-text"
                                                        value="{{ $option->swatch_value ?: '#000000' }}"
                                                        maxlength="7">
                                                        
                                                </div>
                                                
                                            </div>

                                            {{-- Sort order --}}
                                            <div class="col-lg-1">

                                                <label class="form-label d-lg-none">
                                                    Order
                                                </label>

                                                <input type="number" class="form-control option-sort-order"
                                                    value="{{ $option->sort_order }}" min="0" step="1">
                                                <div class="invalid-feedback option-sort-order-error"></div>

                                            </div>

                                            {{-- Remove --}}
                                            <div class="col-lg-1 text-lg-end">

                                                <button type="button" class="btn btn-sm remove-option" title="Remove">

                                                    <i class="ti ti-trash fs-6"></i>

                                                </button>

                                            </div>

                                        </div>

                                    </div>
                                @endforeach

                            </div>

                            <div id="empty-options"
                                class="text-center text-muted py-5
                                    {{ $attribute->options->isNotEmpty() ? 'd-none' : '' }}">

                                No options added yet.

                            </div>

                        </div>


                        <div class="text-end mt-3">

                            <button type="submit" class="btn btn-primary shadow" id="save-options">

                                <span class="btn-text">

                                    <i class="bi bi-floppy me-2"></i>
                                    Save

                                </span>

                                <span class="btn-loading d-none">

                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    Saving...

                                </span>

                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

    {{-- Hidden blueprint used when Add Option is clicked --}}
    <template id="option-row-template">

        <div class="option-row px-3 py-2">

            <input type="hidden" class="option-id" value="">

            <div class="row align-items-center g-2">

                <div class="col-lg-5">

                    <label class="form-label d-lg-none">
                        English Label
                    </label>

                    <input type="text" class="form-control option-label-en" placeholder="Enter English label">

                    <div class="invalid-feedback option-label-en-error"></div>

                </div>

                <div class="col-lg-3">

                    <label class="form-label d-lg-none">
                        Arabic Label
                    </label>

                    <input type="text" class="form-control option-label-ar" placeholder="Enter Arabic label"
                        dir="rtl">

                    <div class="invalid-feedback option-label-ar-error"></div>

                </div>

                <div class="col-lg-2 color-column">

                    <label class="form-label d-lg-none">
                        Color
                    </label>

                    <div class="d-flex align-items-center gap-2">

                        <input type="color" class="form-control form-control-color option-color" value="#000000">

                        <input type="text" class="form-control option-color-text" value="#000000" maxlength="7">

                    </div>

                </div>

                <div class="col-lg-1">

                    <label class="form-label d-lg-none">
                        Order
                    </label>

                    <input type="number" class="form-control option-sort-order" value="0" min="0"
                        step="1">

                    <div class="invalid-feedback option-sort-order-error"></div>
                </div>

                <div class="col-lg-1 text-lg-end">

                    <button type="button" class="btn btn-sm remove-option" title="Remove">

                        <i class="ti ti-trash fs-6"></i>

                    </button>

                </div>

            </div>

        </div>

    </template>
    <script>
        window.routes = {
            attributes: "{{ route('admin.attributes.index') }}",
        };
    </script>
</x-admin-main>
