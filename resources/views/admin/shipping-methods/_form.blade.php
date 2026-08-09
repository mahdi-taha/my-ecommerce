@php($isEdit = isset($shippingMethod))

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Shipping Method</h5>
            </div>
            <div class="card-body">
                @if ($isEdit)
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <input type="text" class="form-control" value="{{ $shippingMethod->code }}" readonly>
                        <div class="form-text">The code is immutable after creation.</div>
                    </div>
                @else
                    <div class="mb-3">
                        <label for="code" class="form-label">Code *</label>
                        <input type="text" id="code" name="code" value="{{ old('code') }}"
                            class="form-control @error('code') is-invalid @enderror" required>
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @endif

                <div class="mb-3">
                    <label for="name" class="form-label">Name *</label>
                    <input type="text" id="name" name="name"
                        value="{{ old('name', $shippingMethod->name ?? '') }}"
                        class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="type" class="form-label">Type *</label>
                    <select id="type" name="type" class="form-select @error('type') is-invalid @enderror"
                        required>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(old('type', isset($shippingMethod) ? $shippingMethod->type->value : '') === $type->value)>
                                {{ str($type->value)->replace('_', ' ')->title() }}
                            </option>
                        @endforeach
                    </select>
                    @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="amount" class="form-label">Amount *</label>
                    <input type="number" id="amount" name="amount" min="0" step="0.0001"
                        value="{{ old('amount', $shippingMethod->amount ?? '') }}"
                        class="form-control @error('amount') is-invalid @enderror" required>
                    @error('amount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="4"
                        class="form-control @error('description') is-invalid @enderror">{{ old('description', $shippingMethod->description ?? '') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Settings</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="sort_order" class="form-label">Sort Order *</label>
                    <input type="number" id="sort_order" name="sort_order" min="0" step="1"
                        value="{{ old('sort_order', $shippingMethod->sort_order ?? 0) }}"
                        class="form-control @error('sort_order') is-invalid @enderror" required>
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input type="checkbox" id="is_active" name="is_active" value="1"
                        class="form-check-input @error('is_active') is-invalid @enderror" @checked(old('is_active', $shippingMethod->is_active ?? false))>
                    <label for="is_active" class="form-check-label">Active</label>
                    @error('is_active')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>
</div>

<div class="text-end mt-3">
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
