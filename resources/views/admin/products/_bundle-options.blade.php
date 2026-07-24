@php
    $bundleOptionsInput = old('bundle_options');
@endphp

<div class="inputs-container shadow pb-3 px-3 rounded" style="border: 1px solid #d4d9e4;"
    id="bundle-options-editor">
    <div class="d-flex justify-content-between align-items-center py-3">
        <div>
            <h5 class="mb-1">Bundle Options</h5>
            <p class="text-muted mb-0">Changes are saved with the Product Save button.</p>
        </div>
        <button type="button" class="btn btn-outline-primary" id="add-bundle-option">
            <i class="ti ti-plus me-1"></i>Add Option
        </button>
    </div>

    @error('bundle_options')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <div id="bundle-option-list">
        @if ($bundleOptionsInput === null)
        @foreach ($product->bundleOptions as $optionIndex => $bundleOption)
            @php
                $optionKey = 'existing_'.$bundleOption->id;
                $optionEnglish = $bundleOption->translations->firstWhere('locale', 'en');
                $optionArabic = $bundleOption->translations->firstWhere('locale', 'ar');
            @endphp
            <div class="bundle-option-row border rounded p-3 mb-3" data-option-key="{{ $optionKey }}">
                <input type="hidden" name="bundle_options[{{ $optionKey }}][id]" value="{{ $bundleOption->id }}">
                <input type="hidden" class="bundle-option-deleted" name="bundle_options[{{ $optionKey }}][deleted]" value="0">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Option</h6>
                    <button type="button" class="btn btn-outline-danger btn-sm remove-bundle-option">Delete</button>
                </div>
                <div class="row bundle-option-fields">
                    <div class="col-lg-4 mb-3">
                        <label class="form-label">English Title *</label>
                        <input type="text" name="bundle_options[{{ $optionKey }}][title_en]" class="form-control"
                            value="{{ old('bundle_options.'.$optionKey.'.title_en', $optionEnglish?->title) }}" required>
                    </div>
                    <div class="col-lg-4 mb-3" dir="rtl">
                        <label class="form-label">Arabic Title *</label>
                        <input type="text" name="bundle_options[{{ $optionKey }}][title_ar]" class="form-control"
                            value="{{ old('bundle_options.'.$optionKey.'.title_ar', $optionArabic?->title) }}" required>
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label class="form-label">Type *</label>
                        <select name="bundle_options[{{ $optionKey }}][type]" class="form-select bundle-option-type" required>
                            @foreach (['select', 'radio', 'checkbox', 'multiselect'] as $type)
                                <option value="{{ $type }}" @selected(old('bundle_options.'.$optionKey.'.type', $bundleOption->type) === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label class="form-label">Sort Order *</label>
                        <input type="number" min="0" name="bundle_options[{{ $optionKey }}][sort_order]" class="form-control"
                            value="{{ old('bundle_options.'.$optionKey.'.sort_order', $bundleOption->sort_order) }}" required>
                    </div>
                    <div class="col-lg-2 mb-3 bundle-selection-limit">
                        <label class="form-label">Minimum *</label>
                        <input type="number" min="0" name="bundle_options[{{ $optionKey }}][min_selections]" class="form-control"
                            value="{{ old('bundle_options.'.$optionKey.'.min_selections', $bundleOption->min_selections) }}">
                    </div>
                    <div class="col-lg-2 mb-3 bundle-selection-limit">
                        <label class="form-label">Maximum *</label>
                        <input type="number" min="1" name="bundle_options[{{ $optionKey }}][max_selections]" class="form-control"
                            value="{{ old('bundle_options.'.$optionKey.'.max_selections', $bundleOption->max_selections) }}">
                    </div>
                    <div class="col-lg-2 mb-3">
                        <input type="hidden" name="bundle_options[{{ $optionKey }}][is_required]" value="0">
                        <div class="form-check form-switch mt-4">
                            <input type="checkbox" class="form-check-input" name="bundle_options[{{ $optionKey }}][is_required]"
                                value="1" @checked((bool) old('bundle_options.'.$optionKey.'.is_required', $bundleOption->is_required))>
                            <label class="form-check-label">Required</label>
                        </div>
                    </div>
                </div>
                <div class="bundle-items ms-lg-4">
                    @foreach ($bundleOption->items as $item)
                        @include('admin.products._bundle-item-row', ['optionKey' => $optionKey, 'itemKey' => 'existing_'.$item->id, 'item' => $item])
                    @endforeach
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm add-bundle-item">+ Add Product</button>
            </div>
        @endforeach
        @endif
    </div>

    <template id="bundle-option-template">
        <div class="bundle-option-row border rounded p-3 mb-3" data-option-key="__OPTION__">
            <input type="hidden" class="bundle-option-deleted" name="bundle_options[__OPTION__][deleted]" value="0">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Option</h6>
                <button type="button" class="btn btn-outline-danger btn-sm remove-bundle-option">Delete</button>
            </div>
            <div class="row bundle-option-fields">
                <div class="col-lg-4 mb-3"><label class="form-label">English Title *</label><input type="text" name="bundle_options[__OPTION__][title_en]" class="form-control" required></div>
                <div class="col-lg-4 mb-3" dir="rtl"><label class="form-label">Arabic Title *</label><input type="text" name="bundle_options[__OPTION__][title_ar]" class="form-control" required></div>
                <div class="col-lg-2 mb-3"><label class="form-label">Type *</label><select name="bundle_options[__OPTION__][type]" class="form-select bundle-option-type" required><option value="select">Select</option><option value="radio">Radio</option><option value="checkbox">Checkbox</option><option value="multiselect">Multiselect</option></select></div>
                <div class="col-lg-2 mb-3"><label class="form-label">Sort Order *</label><input type="number" min="0" name="bundle_options[__OPTION__][sort_order]" class="form-control" value="0" required></div>
                <div class="col-lg-2 mb-3 bundle-selection-limit"><label class="form-label">Minimum *</label><input type="number" min="0" name="bundle_options[__OPTION__][min_selections]" class="form-control" value="0"></div>
                <div class="col-lg-2 mb-3 bundle-selection-limit"><label class="form-label">Maximum *</label><input type="number" min="1" name="bundle_options[__OPTION__][max_selections]" class="form-control" value="1"></div>
                <div class="col-lg-2 mb-3"><input type="hidden" name="bundle_options[__OPTION__][is_required]" value="0"><div class="form-check form-switch mt-4"><input type="checkbox" class="form-check-input" name="bundle_options[__OPTION__][is_required]" value="1"><label class="form-check-label">Required</label></div></div>
            </div>
            <div class="bundle-items ms-lg-4"></div>
            <button type="button" class="btn btn-outline-secondary btn-sm add-bundle-item">+ Add Product</button>
        </div>
    </template>

    <template id="bundle-item-template">
        @include('admin.products._bundle-item-row', ['optionKey' => '__OPTION__', 'itemKey' => '__ITEM__', 'item' => null])
    </template>
</div>

@if ($bundleOptionsInput !== null)
    <script>window.bundleOptionsOldInput = @json($bundleOptionsInput);</script>
@endif
