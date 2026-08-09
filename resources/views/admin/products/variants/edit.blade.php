@php
    $combination = $variant->attributeValues->map(function ($value) {
        $attribute = $value->attribute?->translations->firstWhere('locale', 'en')?->admin_name
            ?? $value->attribute?->code
            ?? 'Attribute';
        $option = $value->option?->translations->firstWhere('locale', 'en')?->label
            ?? 'Option #'.$value->attribute_option_id;

        return $attribute.': '.$option;
    })->implode(' / ');
@endphp

<x-admin-main page="Edit Variant">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/products.js'])
    </x-slot>

    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />

        <div class="body-wrapper">
            <x-admin-topbar />

            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h4><b>Edit Variant</b></h4>
                            <p class="text-muted mb-0">{{ $combination }}</p>
                        </div>
                        <div class="col-4 text-end">
                            <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-transparent">Back</a>
                        </div>
                    </div>

                    <hr>

                    <nav class="mb-3 d-flex flex-wrap gap-2" aria-label="Variant sections">
                        <a href="#general" class="btn btn-sm btn-outline-primary">General</a>
                        <a href="#images" class="btn btn-sm btn-outline-primary">Images</a>
                    </nav>

                    <form action="{{ route('admin.products.variants.update', [$product, $variant]) }}" method="POST"
                        enctype="multipart/form-data" onsubmit="disableSubmitButton(this)">
                        @csrf
                        @method('PUT')

                        <section id="general" class="inputs-container shadow mt-3 pb-3 px-3 rounded" style="border: 1px solid #d4d9e4;">
                            <h5 class="mb-4 mt-3">Variant Details</h5>

                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label for="sku" class="form-label">SKU *</label>
                                    <input type="text" id="sku" name="sku" class="form-control @error('sku') border-danger @enderror"
                                        value="{{ old('sku', $variant->sku) }}" required>
                                    @error('sku')<p class="text-danger">{{ $message }}</p>@enderror
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label for="product_number" class="form-label">Product Number</label>
                                    <input type="text" id="product_number" name="product_number"
                                        class="form-control @error('product_number') border-danger @enderror"
                                        value="{{ old('product_number', $variant->product_number) }}">
                                    @error('product_number')<p class="text-danger">{{ $message }}</p>@enderror
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label for="price" class="form-label">Price *</label>
                                    <input type="number" step="0.0001" min="0" id="price" name="price"
                                        class="form-control @error('price') border-danger @enderror"
                                        value="{{ old('price', $variant->price) }}" required>
                                    @error('price')<p class="text-danger">{{ $message }}</p>@enderror
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label for="special_price" class="form-label">Special Price</label>
                                    <input type="number" step="0.0001" min="0" id="special_price" name="special_price"
                                        class="form-control @error('special_price') border-danger @enderror"
                                        value="{{ old('special_price', $variant->special_price) }}">
                                    @error('special_price')<p class="text-danger">{{ $message }}</p>@enderror
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label for="special_price_from" class="form-label">Special Price From</label>
                                    <input type="datetime-local" id="special_price_from" name="special_price_from"
                                        class="form-control @error('special_price_from') border-danger @enderror"
                                        value="{{ old('special_price_from', $variant->special_price_from?->format('Y-m-d\\TH:i')) }}">
                                    @error('special_price_from')<p class="text-danger">{{ $message }}</p>@enderror
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label for="special_price_to" class="form-label">Special Price To</label>
                                    <input type="datetime-local" id="special_price_to" name="special_price_to"
                                        class="form-control @error('special_price_to') border-danger @enderror"
                                        value="{{ old('special_price_to', $variant->special_price_to?->format('Y-m-d\\TH:i')) }}">
                                    @error('special_price_to')<p class="text-danger">{{ $message }}</p>@enderror
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label">Current Stock</label>
                                    <input type="text" id="quantity" class="form-control"
                                        value="{{ $variant->inventory?->quantity ?? 0 }}" readonly>
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label d-block">Status</label>
                                    <input type="hidden" name="status" value="0">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="status"
                                            name="status" value="1" @checked((bool) old('status', $variant->status))>
                                        <label class="form-check-label" for="status">Active</label>
                                    </div>
                                    @error('status')<p class="text-danger">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </section>

                        <section id="images" class="inputs-container shadow mt-4 pb-3 px-3 rounded" style="border: 1px solid #d4d9e4;">
                            <h5 class="mb-4 mt-3">Variant Images</h5>
                            <input type="file" id="new_images" name="new_images[]" class="form-control mb-3"
                                accept="image/jpeg,image/png,image/webp" multiple>
                            <div class="form-text text-muted">Recommended: 1200 × 1200 px (1:1). Storefront images use contain; Admin previews may be center-cropped.</div>
                            @error('new_images.*')<p class="text-danger">{{ $message }}</p>@enderror
                            @error('base_image')<p class="text-danger">{{ $message }}</p>@enderror
                            @error('deleted_image_ids')<p class="text-danger">{{ $message }}</p>@enderror

                            @if ($variant->images->isEmpty() && $product->images->isNotEmpty())
                                @php $parentPreview = $product->images->firstWhere('is_base', true) ?? $product->images->first(); @endphp
                                <div class="alert alert-info d-flex align-items-center gap-3">
                                    <img src="{{ Storage::disk('public')->url($parentPreview->path) }}" alt="" width="60" height="60"
                                        class="rounded object-fit-cover">
                                    <span>The parent image will be used as the storefront fallback. It is not stored on this variant.</span>
                                </div>
                            @endif

                            <div class="row g-3 mb-3">
                                @foreach ($variant->images as $image)
                                    @php $imageWasDeleted = in_array((string) $image->id, array_map('strval', old('deleted_image_ids', [])), true); @endphp
                                    <div class="col-md-4 product-image-row {{ $imageWasDeleted ? 'd-none' : '' }}">
                                        <div class="border rounded p-2">
                                            <img src="{{ Storage::disk('public')->url($image->path) }}" alt=""
                                                class="img-fluid rounded mb-2" style="height: 160px; width: 100%; object-fit: cover;">
                                            <div class="form-check mb-2">
                                                <input type="radio" class="form-check-input product-base-image" name="base_image"
                                                    id="base_existing_{{ $image->id }}" value="existing:{{ $image->id }}"
                                                    @checked(! $imageWasDeleted && old('base_image', $image->is_base ? 'existing:'.$image->id : null) === 'existing:'.$image->id)>
                                                <label class="form-check-label" for="base_existing_{{ $image->id }}">Base Image</label>
                                            </div>
                                            <label class="form-label" for="sort_existing_{{ $image->id }}">Sort Order</label>
                                            <input type="number" min="0" id="sort_existing_{{ $image->id }}"
                                                name="existing_image_sort_orders[{{ $image->id }}]" class="form-control mb-2"
                                                value="{{ old('existing_image_sort_orders.'.$image->id, $image->sort_order) }}">
                                            <input type="checkbox" class="d-none product-delete-image" name="deleted_image_ids[]"
                                                value="{{ $image->id }}" @checked($imageWasDeleted)>
                                            <button type="button" class="btn btn-outline-danger btn-sm product-image-delete">Remove</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div id="new-product-image-previews" class="row g-3"></div>
                        </section>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary shadow">
                                <i class="ti ti-device-floppy me-1"></i>
                                <span class="btn-text">Save</span>
                                <span class="btn-loading d-none">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
