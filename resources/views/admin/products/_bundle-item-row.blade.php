<div class="bundle-item-row border rounded p-2 mb-2" data-item-key="{{ $itemKey }}">
    @if ($item)
        <input type="hidden" name="bundle_options[{{ $optionKey }}][items][{{ $itemKey }}][id]" value="{{ $item->id }}">
    @endif
    <input type="hidden" class="bundle-item-deleted" name="bundle_options[{{ $optionKey }}][items][{{ $itemKey }}][deleted]" value="0">
    <div class="row align-items-end">
        <div class="col-lg-4 mb-2">
            <label class="form-label">Product *</label>
            <select name="bundle_options[{{ $optionKey }}][items][{{ $itemKey }}][product_id]" class="form-select bundle-product-select" required>
                <option value="">Select Product</option>
                @foreach ($bundleItemProducts as $itemProduct)
                    @php
                        $itemName = $itemProduct->translations->first()?->name
                            ?? $itemProduct->configurable?->translations->first()?->name
                            ?? 'Product';
                    @endphp
                    <option value="{{ $itemProduct->id }}" @selected($item && $item->product_id === $itemProduct->id)>
                        {{ $itemProduct->sku }} - {{ $itemName }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 mb-2"><label class="form-label">Default Quantity *</label><input type="number" min="0.0001" step="0.0001" name="bundle_options[{{ $optionKey }}][items][{{ $itemKey }}][default_quantity]" class="form-control" value="{{ $item?->default_quantity ?? 1 }}" required></div>
        <div class="col-lg-2 mb-2"><label class="form-label">Price Override</label><input type="number" min="0" step="0.0001" name="bundle_options[{{ $optionKey }}][items][{{ $itemKey }}][price_override]" class="form-control" value="{{ $item?->price_override }}"></div>
        <div class="col-lg-1 mb-2"><label class="form-label">Sort *</label><input type="number" min="0" name="bundle_options[{{ $optionKey }}][items][{{ $itemKey }}][sort_order]" class="form-control" value="{{ $item?->sort_order ?? 0 }}" required></div>
        <div class="col-lg-2 mb-2"><input type="hidden" name="bundle_options[{{ $optionKey }}][items][{{ $itemKey }}][is_default]" value="0"><div class="form-check form-switch"><input type="checkbox" class="form-check-input" name="bundle_options[{{ $optionKey }}][items][{{ $itemKey }}][is_default]" value="1" @checked($item?->is_default)><label class="form-check-label">Default</label></div></div>
        <div class="col-lg-1 mb-2"><button type="button" class="btn text-danger p-0 remove-bundle-item" title="Delete"><i class="ti ti-trash fs-6"></i></button></div>
    </div>
</div>
