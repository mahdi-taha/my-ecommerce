@php
    $isEdit = isset($customer);
@endphp

<div class="row">
    <div class="col-12 mb-3">
        <label for="name" class="form-label">Display Name *</label>
        <input type="text" id="name" name="name" class="form-control @error('name') border-danger @enderror"
            value="{{ old('name', $customer->name ?? '') }}" required>
        @error('name')
            <p class="text-danger mt-1 mb-0">{{ $message }}</p>
        @enderror
    </div>

    @unless ($isEdit)
        <div class="col-md-6 mb-3">
            <label for="has_account" class="form-label">Customer Type *</label>
            <select id="has_account" name="has_account" class="form-select @error('has_account') border-danger @enderror"
                required>
                <option value="1" @selected((string) old('has_account', '1') === '1')>Registered account</option>
                <option value="0" @selected((string) old('has_account') === '0')>Manual customer</option>
            </select>
            <small class="text-muted">Manual customers cannot sign in.</small>
            @error('has_account')
                <p class="text-danger mt-1 mb-0">{{ $message }}</p>
            @enderror
        </div>
    @else
        <div class="col-md-6 mb-3">
            <span class="form-label d-block">Customer Type</span>
            <span class="badge {{ $customer->has_account ? 'bg-primary' : 'bg-secondary' }}">
                {{ $customer->has_account ? 'Registered account' : 'Manual customer' }}
            </span>
        </div>
    @endunless

    <div class="col-md-6 mb-3">
        <label for="first_name" class="form-label">First Name *</label>
        <input type="text" id="first_name" name="first_name"
            class="form-control @error('first_name') border-danger @enderror"
            value="{{ old('first_name', $customer->first_name ?? '') }}" required>
        @error('first_name')
            <p class="text-danger mt-1 mb-0">{{ $message }}</p>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="last_name" class="form-label">Last Name *</label>
        <input type="text" id="last_name" name="last_name"
            class="form-control @error('last_name') border-danger @enderror"
            value="{{ old('last_name', $customer->last_name ?? '') }}" required>
        @error('last_name')
            <p class="text-danger mt-1 mb-0">{{ $message }}</p>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="email" class="form-label">Email {{ $customer->has_account ?? false ? '*' : '' }}</label>
        <input type="email" id="email" name="email" class="form-control @error('email') border-danger @enderror"
            value="{{ old('email', $customer->email ?? '') }}">
        @error('email')
            <p class="text-danger mt-1 mb-0">{{ $message }}</p>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" id="phone" name="phone" class="form-control @error('phone') border-danger @enderror"
            value="{{ old('phone', $customer->phone ?? '') }}">
        @error('phone')
            <p class="text-danger mt-1 mb-0">{{ $message }}</p>
        @enderror
    </div>

    @unless ($isEdit)
        <div class="col-md-6 mb-3">
            <label for="password" class="form-label">Password (required for registered accounts)</label>
            <input type="password" id="password" name="password"
                class="form-control @error('password') border-danger @enderror">
            @error('password')
                <p class="text-danger mt-1 mb-0">{{ $message }}</p>
            @enderror
        </div>

        <div class="col-md-6 mb-3">
            <label for="password_confirmation" class="form-label">Confirm Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control">
        </div>
    @endunless

    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check form-switch">
            <input type="checkbox" class="form-check-input @error('is_active') border-danger @enderror" id="is_active"
                name="is_active" value="1" @checked((bool) old('is_active', $customer->is_active ?? true))>
            <label for="is_active" class="form-check-label">Active</label>
        </div>
        @error('is_active')
            <p class="text-danger mt-1 mb-0">{{ $message }}</p>
        @enderror
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
