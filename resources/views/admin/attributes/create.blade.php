<x-admin-main page="Create Attribute">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/js/app.js', 'resources/css/myStyle.css', 'resources/js/admin/attributes.js'])

    </x-slot>
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    @if (session('custom_error'))
                        <div class="alert alert-warning alert-dismissible">
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            {{ session('custom_error') }}
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-6">
                            <h4> <b>Add Attribute</b> </h4>
                        </div>
                        <div class="col-6 text-end">
                            <a href="{{ route('admin.attributes.index') }}" class="btn btn-transparent">Back</a>
                        </div>
                    </div>
                    <hr />
                    <form action="{{ route('admin.attributes.store') }}" method="post"
                        onsubmit="disableSubmitButton(this)">
                        @csrf
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="inputs-container shadow mt-3 pb-3 px-3 rounded"
                                    style="border: 1px solid #d4d9e4;">
                                    <h5 class="mb-4 mt-3">Attribute Information</h5>
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="mb-3 mt-1">
                                                <label for="attribute_code" class="form-label">Code *</label>
                                                <input type="text"
                                                    class="form-control @error('attribute_code') border-danger @enderror"
                                                    id="attribute_code" name="attribute_code"
                                                    value="{{ old('attribute_code') }}" required
                                                    placeholder="Enter attribute code">
                                                @error('attribute_code')
                                                    <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="mb-3 mt-1">
                                                <label for="attribute_name_en" class="form-label">Name (en)*</label>
                                                <input type="text"
                                                    class="form-control @error('attribute_name_en') border-danger @enderror"
                                                    id="attribute_name_en" name="attribute_name_en"
                                                    value="{{ old('attribute_name_en') }}"
                                                    placeholder="Enter English name" required>
                                                @error('attribute_name_en')
                                                    <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="mb-3 mt-1">
                                                <label for="attribute_name_ar" class="form-label">Name (ar)*</label>
                                                <input type="text"
                                                    class="form-control @error('attribute_name_ar') border-danger @enderror"
                                                    id="attribute_name_ar" name="attribute_name_ar"
                                                    value="{{ old('attribute_name_ar') }}"
                                                    placeholder="Enter Arabic name" required>
                                                @error('attribute_name_ar')
                                                    <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="mb-3 mt-1">
                                                <label for="attribute_sort_order" class="form-label">Sort Order</label>
                                                <input type="number" min="0" step="1"
                                                    class="form-control @error('attribute_sort_order') border-danger @enderror"
                                                    id="attribute_sort_order" name="attribute_sort_order"
                                                    value="{{ old('attribute_sort_order', '0') }}"
                                                    placeholder="Enter sort order">
                                                @error('attribute_sort_order')
                                                    <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="mb-3 mt-1">
                                                <label for="attribute_type" class="form-label"><b>Type *</b></label>
                                                <select
                                                    class="form-select @error('attribute_type') border-danger @enderror"
                                                    id="attribute_type" name="attribute_type" required>
                                                    <option value="">Select attribute type</option>
                                                    <option value="select"
                                                        {{ old('attribute_type') == 'select' ? 'selected' : '' }}>
                                                        select</option>
                                                    <option value="multiselect"
                                                        {{ old('attribute_type') == 'multiselect' ? 'selected' : '' }}>
                                                        multiselect</option>
                                                    <option value="text"
                                                        {{ old('attribute_type') == 'text' ? 'selected' : '' }}>text
                                                    </option>
                                                </select>

                                                @error('attribute_type')
                                                    <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="mb-3 mt-1">
                                                <label for="attribute_swatch_type" class="form-label"><b>Swatch
                                                        Type</b></label>
                                                <select
                                                    class="form-select @error('attribute_swatch_type') border-danger @enderror"
                                                    id="attribute_swatch_type" name="attribute_swatch_type">
                                                    <option value="">Select swatch type</option>
                                                    <option value="text"
                                                        {{ old('attribute_swatch_type') == 'text' ? 'selected' : '' }}>
                                                        text</option>
                                                    <option value="dropdown"
                                                        {{ old('attribute_swatch_type') == 'dropdown' ? 'selected' : '' }}>
                                                        dropdown</option>
                                                    <option value="color"
                                                        {{ old('attribute_swatch_type') == 'color' ? 'selected' : '' }}>
                                                        color</option>
                                                </select>
                                                @error('attribute_swatch_type')
                                                    <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12 px-2">
                                <div class="inputs-container shadow mt-3 pb-3 px-3 rounded"
                                    style="border: 1px solid #d4d9e4;">
                                    <h5 class="mb-4 mt-3">Behavior</h5>
                                    <div class="row">
                                        <div class="col-lg-2 py-3 ps-lg-3">
                                            <label class="fs-2 text-dark" style="cursor: pointer;">
                                                <input type="hidden" name="is_required" value="0">
                                                <input type="checkbox"
                                                    class="form-check-input @error('is_required') border-danger @enderror"
                                                    id="is_required" name="is_required" value="1"
                                                    {{ old('is_required') ? 'checked' : '' }}>
                                                Is Required
                                            </label>
                                            @error('is_required')
                                                <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="col-lg-4 py-3">
                                            <label class="fs-2 text-dark" style="cursor: pointer;"
                                                id="is_configurable_label">
                                                <input type="hidden" name="is_configurable" value="0">
                                                <input type="checkbox"
                                                    class="form-check-input @error('is_configurable') border-danger @enderror"
                                                    id="is_configurable" name="is_configurable" value="1"
                                                    {{ old('is_configurable') ? 'checked' : '' }}>
                                                Use To Create Configurable Product
                                            </label>
                                            @error('is_configurable')
                                                <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="col-lg-2 py-3">
                                            <label class="fs-2 text-dark" style="cursor: pointer;">
                                                <input type="hidden" name="is_filterable" value="0">
                                                <input type="checkbox"
                                                    class="form-check-input @error('is_filterable') border-danger @enderror"
                                                    id="is_filterable" name="is_filterable" value="1"
                                                    {{ old('is_filterable') ? 'checked' : '' }}>
                                                Use in Filters
                                            </label>
                                            @error('is_filterable')
                                                <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="col-lg-4 py-3">
                                            <label class="fs-2 text-dark" style="cursor: pointer;">
                                                <input type="hidden" name="is_visible_on_front" value="0">
                                                <input type="checkbox"
                                                    class="form-check-input @error('is_visible_on_front') border-danger @enderror"
                                                    id="is_visible_on_front" name="is_visible_on_front"
                                                    value="1" {{ old('is_visible_on_front') ? 'checked' : '' }}>
                                                Visible on Product View Page on Front-end
                                            </label>
                                            @error('is_visible_on_front')
                                                <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr class="mt-4" />
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary shadow">

                                    <span class="btn-text">

                                        <i class="bi bi-floppy me-2"></i>

                                        Save

                                    </span>

                                    <span class="btn-loading d-none">
                                        Saving...
                                    </span>

                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
