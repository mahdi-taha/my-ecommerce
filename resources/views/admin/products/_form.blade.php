@if ($isEdit)
    @include('admin.products._standard-product-edit-form')
@else
    <form action="{{ route('admin.products.store') }}" method="POST" onsubmit="disableSubmitButton(this)">
        @csrf

        <section id="general" class="inputs-container shadow mt-3 pb-3 px-3 rounded" style="border: 1px solid #d4d9e4;">
            <h5 class="mb-4 mt-3">Product Information</h5>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="type" class="form-label">Product Type *</label>
                    <select class="form-select @error('type') border-danger @enderror" id="type" name="type" required>
                        <option value="">Select Product Type</option>
                        <option value="simple" @selected(old('type') === 'simple')>Simple Product</option>
                        <option value="configurable" @selected(old('type') === 'configurable')>Configurable Product</option>
                    </select>
                    @error('type')<p class="text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="sku" class="form-label">SKU *</label>
                    <input type="text" class="form-control @error('sku') border-danger @enderror" id="sku"
                        name="sku" value="{{ old('sku') }}" required>
                    @error('sku')<p class="text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="product_number" class="form-label">Product Number</label>
                    <input type="text" class="form-control @error('product_number') border-danger @enderror"
                        id="product_number" name="product_number" value="{{ old('product_number') }}">
                    @error('product_number')<p class="text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="col-lg-6 mb-3" id="configurable-base-price-field">
                    <label for="price" class="form-label">Configurable Base Price *</label>
                    <input type="number" step="0.0001" min="0"
                        class="form-control @error('price') border-danger @enderror" id="price" name="price"
                        value="{{ old('price') }}">
                    <div class="form-text">Used as the initial price for generated variants.</div>
                    @error('price')<p class="text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="product_name_en" class="form-label">English Name *</label>
                    <input type="text" class="form-control @error('product_name_en') border-danger @enderror"
                        id="product_name_en" name="product_name_en" value="{{ old('product_name_en') }}" required>
                    @error('product_name_en')<p class="text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="product_name_ar" class="form-label">Arabic Name *</label>
                    <input type="text" class="form-control @error('product_name_ar') border-danger @enderror"
                        id="product_name_ar" name="product_name_ar" value="{{ old('product_name_ar') }}" dir="rtl" required>
                    @error('product_name_ar')<p class="text-danger">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <hr class="mt-4">
        <div class="text-end">
            <button type="submit" class="btn btn-primary shadow">
                <span class="btn-text">Continue</span>
                <span class="btn-loading d-none">Saving...</span>
            </button>
        </div>
    </form>
@endif
