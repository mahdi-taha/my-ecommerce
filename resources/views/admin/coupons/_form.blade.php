@php
    $isEdit = isset($coupon);
    $codeLocked = $isEdit && $coupon->usages_count > 0;
    $localDate = fn($value) => $value?->timezone($storeTimezone)->format('Y-m-d\TH:i');
@endphp

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Coupon</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="code" class="form-label">Code *</label>
                        <input type="text" id="code" name="code"
                            value="{{ old('code', $coupon->code ?? '') }}"
                            class="form-control @error('code') is-invalid @enderror" @readonly($codeLocked) required>
                        @if ($codeLocked)
                            <div class="form-text">The code is immutable because this Coupon has usage history.</div>
                        @endif
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="name" class="form-label">Name *</label>
                        <input type="text" id="name" name="name"
                            value="{{ old('name', $coupon->name ?? '') }}"
                            class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="type" class="form-label">Type *</label>
                        <select id="type" name="type" class="form-select @error('type') is-invalid @enderror"
                            required>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected(old('type', isset($coupon) ? $coupon->type->value : '') === $type->value)>
                                    {{ str($type->value)->title() }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="value" class="form-label">Value *</label>
                        <input type="number" id="value" name="value" min="0.0001" step="0.0001"
                            value="{{ old('value', $coupon->value ?? '') }}"
                            class="form-control @error('value') is-invalid @enderror" required>
                        @error('value')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="starts_at" class="form-label">Starts At</label>
                        <input type="datetime-local" id="starts_at" name="starts_at"
                            value="{{ old('starts_at', $localDate($coupon->starts_at ?? null)) }}"
                            class="form-control @error('starts_at') is-invalid @enderror">
                        @error('starts_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="ends_at" class="form-label">Ends At</label>
                        <input type="datetime-local" id="ends_at" name="ends_at"
                            value="{{ old('ends_at', $localDate($coupon->ends_at ?? null)) }}"
                            class="form-control @error('ends_at') is-invalid @enderror">
                        @error('ends_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="minimum_subtotal" class="form-label">Minimum Subtotal</label>
                        <input type="number" id="minimum_subtotal" name="minimum_subtotal" min="0"
                            step="0.0001" value="{{ old('minimum_subtotal', $coupon->minimum_subtotal ?? '') }}"
                            class="form-control @error('minimum_subtotal') is-invalid @enderror">
                        @error('minimum_subtotal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Configured Timezone</label>
                        <input class="form-control" value="{{ $storeTimezone }}" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Limits and Status</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="usage_limit" class="form-label">Global Usage Limit</label>
                    <input type="number" id="usage_limit" name="usage_limit" min="1" step="1"
                        value="{{ old('usage_limit', $coupon->usage_limit ?? '') }}"
                        class="form-control @error('usage_limit') is-invalid @enderror">
                    @error('usage_limit')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="per_customer_usage_limit" class="form-label">Per-Customer Usage Limit</label>
                    <input type="number" id="per_customer_usage_limit" name="per_customer_usage_limit" min="1"
                        step="1"
                        value="{{ old('per_customer_usage_limit', $coupon->per_customer_usage_limit ?? '') }}"
                        class="form-control @error('per_customer_usage_limit') is-invalid @enderror">
                    @error('per_customer_usage_limit')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                        @checked(old('is_active', $coupon->is_active ?? false))>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
                <input type="hidden" name="is_first_order_only" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_first_order_only"
                        name="is_first_order_only" value="1" @checked(old('is_first_order_only', $coupon->is_first_order_only ?? false))>
                    <label class="form-check-label" for="is_first_order_only">First Order Only</label>
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
