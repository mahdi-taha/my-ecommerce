@php
    $englishTranslation = $product->translations->firstWhere('locale', 'en');
    $arabicTranslation = $product->translations->firstWhere('locale', 'ar');
    $selectedCategoryIds = old('category_ids', $product->categories->pluck('id')->map(fn ($id) => (string) $id)->all());
    $attributeValues = $product->attributeValues->groupBy('attribute_id');
@endphp

<nav class="mb-4 d-flex flex-wrap gap-2">
    @foreach (['general' => 'General', 'english' => 'English Content', 'arabic' => 'Arabic Content', 'images' => 'Images', 'attributes' => 'Attributes', 'seo' => 'SEO', 'variants' => 'Variants'] as $anchor => $label)
        <a href="#configurable-{{ $anchor }}" class="btn btn-outline-primary btn-sm">{{ $label }}</a>
    @endforeach
</nav>

<form action="{{ route('admin.products.update', $product) }}" method="POST" enctype="multipart/form-data"
    onsubmit="disableSubmitButton(this)">
    @csrf
    @method('PUT')

    <section id="configurable-general" class="inputs-container shadow mb-4 pb-3 px-3 rounded" style="border: 1px solid #d4d9e4;">
        <h5 class="mb-4 mt-3">General</h5>
        <div class="row">
            <div class="col-lg-4 mb-3">
                <label class="form-label">Product Type</label>
                <input type="text" class="form-control" value="Configurable" disabled>
            </div>
            <div class="col-lg-4 mb-3">
                <label for="sku" class="form-label">SKU *</label>
                <input type="text" id="sku" name="sku" class="form-control @error('sku') border-danger @enderror"
                    value="{{ old('sku', $product->sku) }}" required>
                @error('sku')<p class="text-danger">{{ $message }}</p>@enderror
            </div>
            <div class="col-lg-4 mb-3">
                <label for="product_number" class="form-label">Product Number</label>
                <input type="text" id="product_number" name="product_number"
                    class="form-control @error('product_number') border-danger @enderror"
                    value="{{ old('product_number', $product->product_number) }}">
                @error('product_number')<p class="text-danger">{{ $message }}</p>@enderror
            </div>
            <div class="col-lg-6 mb-3">
                <label for="price" class="form-label">Base Price *</label>
                <input type="number" step="0.0001" min="0" id="price" name="price"
                    class="form-control @error('price') border-danger @enderror"
                    value="{{ old('price', $product->price) }}" required>
                @error('price')<p class="text-danger">{{ $message }}</p>@enderror
                <small class="text-muted">Used only as the initial price for newly generated variants.</small>
            </div>
            <div class="col-lg-6 mb-3">
                <label class="form-label d-block">Product Settings</label>
                <input type="hidden" name="is_visible_individually" value="{{ $product->is_visible_individually ? 1 : 0 }}">
                @foreach (['is_new' => 'New Product', 'is_featured' => 'Featured', 'status' => 'Active'] as $field => $label)
                    <input type="hidden" name="{{ $field }}" value="0">
                    <div class="form-check form-switch form-check-inline">
                        <input class="form-check-input" type="checkbox" id="{{ $field }}" name="{{ $field }}"
                            value="1" @checked((bool) old($field, $product->{$field}))>
                        <label class="form-check-label" for="{{ $field }}">{{ $label }}</label>
                    </div>
                    @error($field)<p class="text-danger">{{ $message }}</p>@enderror
                @endforeach
            </div>
            <div class="col-12 mb-3">
                <label for="category_ids" class="form-label">Categories</label>
                <select id="category_ids" name="category_ids[]" class="form-select product-category-select"
                    multiple data-placeholder="Select Categories">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(in_array((string) $category->id, $selectedCategoryIds, true))>
                            {{ $category->display_path }}
                        </option>
                    @endforeach
                </select>
                @error('category_ids')<p class="text-danger">{{ $message }}</p>@enderror
                @error('category_ids.*')<p class="text-danger">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    @foreach (['en' => ['English Content', $englishTranslation], 'ar' => ['Arabic Content', $arabicTranslation]] as $locale => [$heading, $translation])
        <section id="configurable-{{ $locale === 'en' ? 'english' : 'arabic' }}" class="inputs-container shadow mb-4 pb-3 px-3 rounded"
            style="border: 1px solid #d4d9e4;" @if ($locale === 'ar') dir="rtl" @endif>
            <h5 class="mb-4 mt-3">{{ $heading }}</h5>
            <div class="mb-3">
                <label for="product_name_{{ $locale }}" class="form-label">Name *</label>
                <input type="text" id="product_name_{{ $locale }}" name="product_name_{{ $locale }}"
                    class="form-control @error('product_name_'.$locale) border-danger @enderror"
                    value="{{ old('product_name_'.$locale, $translation?->name) }}" required>
                @error('product_name_'.$locale)<p class="text-danger">{{ $message }}</p>@enderror
            </div>
            <div class="mb-3">
                <label for="short_description_{{ $locale }}" class="form-label">Short Description</label>
                <textarea id="short_description_{{ $locale }}" name="short_description_{{ $locale }}" class="form-control" rows="3">{{ old('short_description_'.$locale, $translation?->short_description) }}</textarea>
                @error('short_description_'.$locale)<p class="text-danger">{{ $message }}</p>@enderror
            </div>
            <div class="mb-3">
                <label for="description_{{ $locale }}" class="form-label">Description</label>
                <textarea id="description_{{ $locale }}" name="description_{{ $locale }}" class="form-control" rows="6">{{ old('description_'.$locale, $translation?->description) }}</textarea>
                @error('description_'.$locale)<p class="text-danger">{{ $message }}</p>@enderror
            </div>
        </section>
    @endforeach

    <section id="configurable-images" class="inputs-container shadow mb-4 pb-3 px-3 rounded" style="border: 1px solid #d4d9e4;">
        <h5 class="mb-4 mt-3">Images</h5>
        <input type="file" id="new_images" name="new_images[]" class="form-control mb-3"
            accept="image/jpeg,image/png,image/webp" multiple>
        <div class="form-text text-muted">Recommended: 1200 × 1200 px (1:1). Storefront images use contain; Admin previews may be center-cropped.</div>
        @error('new_images.*')<p class="text-danger">{{ $message }}</p>@enderror
        @error('base_image')<p class="text-danger">{{ $message }}</p>@enderror
        <div class="row g-3 mb-3">
            @foreach ($product->images as $image)
                @php $imageWasDeleted = in_array((string) $image->id, array_map('strval', old('deleted_image_ids', [])), true); @endphp
                <div class="col-md-4 product-image-row {{ $imageWasDeleted ? 'd-none' : '' }}">
                    <div class="border rounded p-2">
                        <img src="{{ Storage::disk('public')->url($image->path) }}" alt="" class="img-fluid rounded mb-2"
                            style="height: 160px; width: 100%; object-fit: cover;">
                        <div class="form-check mb-2">
                            <input type="radio" class="form-check-input product-base-image" name="base_image"
                                id="base_existing_{{ $image->id }}" value="existing:{{ $image->id }}"
                                @checked(! $imageWasDeleted && old('base_image', $image->is_base ? 'existing:'.$image->id : null) === 'existing:'.$image->id)>
                            <label for="base_existing_{{ $image->id }}" class="form-check-label">Base Image</label>
                        </div>
                        <label for="sort_existing_{{ $image->id }}" class="form-label">Sort Order</label>
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

    <section id="configurable-attributes" class="inputs-container shadow mb-4 pb-3 px-3 rounded" style="border: 1px solid #d4d9e4;">
        <h5 class="mb-4 mt-3">Common Attributes</h5>
        @error('attributes')<p class="text-danger">{{ $message }}</p>@enderror
        @forelse ($attributes as $attribute)
            @php
                $existingValues = $attributeValues->get($attribute->id, collect());
                $oldKey = 'attributes.'.$attribute->id;
                $label = $attribute->translations->first()?->admin_name ?? $attribute->code;
            @endphp
            <div class="mb-3">
                <label class="form-label" for="attribute_{{ $attribute->id }}">{{ $label }}{{ $attribute->is_required ? ' *' : '' }}</label>
                @if ($attribute->type === 'text')
                    <input type="text" id="attribute_{{ $attribute->id }}" name="attributes[{{ $attribute->id }}]"
                        class="form-control @error($oldKey) is-invalid @enderror" value="{{ old($oldKey, $existingValues->first()?->value) }}" @required($attribute->is_required)>
                @elseif ($attribute->type === 'select')
                    <select id="attribute_{{ $attribute->id }}" name="attributes[{{ $attribute->id }}]" class="form-select @error($oldKey) is-invalid @enderror" @required($attribute->is_required)>
                        <option value="">Select {{ $label }}</option>
                        @foreach ($attribute->options as $option)
                            <option value="{{ $option->id }}" @selected((string) old($oldKey, $existingValues->first()?->attribute_option_id) === (string) $option->id)>
                                {{ $option->translations->first()?->label ?? 'Option #'.$option->id }}
                            </option>
                        @endforeach
                    </select>
                @else
                    @php $selectedOptions = old($oldKey, $existingValues->pluck('attribute_option_id')->map(fn ($id) => (string) $id)->all()); @endphp
                    <select id="attribute_{{ $attribute->id }}" name="attributes[{{ $attribute->id }}][]"
                        class="form-select product-attribute-multiselect {{ $errors->has($oldKey) || $errors->has($oldKey.'.*') ? 'is-invalid' : '' }}" multiple data-placeholder="Select {{ $label }}" @required($attribute->is_required)>
                        @foreach ($attribute->options as $option)
                            <option value="{{ $option->id }}" @selected(in_array((string) $option->id, $selectedOptions, true))>
                                {{ $option->translations->first()?->label ?? 'Option #'.$option->id }}
                            </option>
                        @endforeach
                    </select>
                @endif
                @error($oldKey)<p class="text-danger">{{ $message }}</p>@enderror
                @error($oldKey.'.*')<p class="text-danger">{{ $message }}</p>@enderror
            </div>
        @empty
            <p class="text-muted">No Attributes</p>
        @endforelse
    </section>

    <section id="configurable-seo" class="inputs-container shadow mb-4 pb-3 px-3 rounded" style="border: 1px solid #d4d9e4;">
        <h5 class="mb-4 mt-3">SEO</h5>
        <div class="row">
            @foreach (['en' => ['English', $englishTranslation], 'ar' => ['Arabic', $arabicTranslation]] as $locale => [$label, $translation])
                <div class="col-lg-6" @if ($locale === 'ar') dir="rtl" @endif>
                    <h6>{{ $label }}</h6>
                    @foreach (['url_key' => 'URL Key', 'meta_title' => 'Meta Title', 'meta_keywords' => 'Meta Keywords'] as $field => $fieldLabel)
                        <div class="mb-3">
                            <label for="{{ $field }}_{{ $locale }}" class="form-label">{{ $fieldLabel }}{{ $field === 'url_key' ? ' *' : '' }}</label>
                            <input type="text" id="{{ $field }}_{{ $locale }}" name="{{ $field }}_{{ $locale }}"
                                class="form-control" value="{{ old($field.'_'.$locale, $translation?->{$field}) }}" @required($field === 'url_key')>
                            @error($field.'_'.$locale)<p class="text-danger">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <div class="mb-3">
                        <label for="meta_description_{{ $locale }}" class="form-label">Meta Description</label>
                        <textarea id="meta_description_{{ $locale }}" name="meta_description_{{ $locale }}" class="form-control" rows="4">{{ old('meta_description_'.$locale, $translation?->meta_description) }}</textarea>
                        @error('meta_description_'.$locale)<p class="text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section id="configurable-variants" class="inputs-container shadow mb-4 pb-3 px-3 rounded" style="border: 1px solid #d4d9e4;">
        <h5 class="mt-3">Variants</h5>
        <p class="text-muted mb-0">{{ $product->variants_count }} generated variants</p>
    </section>

    <div class="text-end">
        <button type="submit" class="btn btn-primary shadow">
            <i class="ti ti-device-floppy me-1"></i>
            <span class="btn-text">Save</span>
            <span class="btn-loading d-none">Saving...</span>
        </button>
    </div>
</form>
