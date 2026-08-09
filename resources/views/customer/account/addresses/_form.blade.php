@php($address = $address ?? null)
@csrf
@if ($address)
    @method('PUT')
@endif

<div class="row g-3">
    @foreach ([
        'label' => ['label', false],
        'first_name' => ['first_name', true],
        'last_name' => ['last_name', true],
        'company' => ['company', false],
        'phone' => ['phone', true],
        'country_code' => ['country', true],
        'state' => ['governorate', true],
        'city' => ['city', true],
        'address_line_1' => ['address_line_1', true],
        'address_line_2' => ['address_line_2', false],
        'postal_code' => ['postal_code', false],
    ] as $field => [$translationKey, $required])
        <div class="{{ str_starts_with($field, 'address_line') ? 'col-12' : 'col-md-6' }}">
            <label class="form-label text-dark" for="{{ $field }}">
                <b>
                    {{ __('shop.account.addresses.fields.' . $translationKey) }}@if ($required)
                        *
                    @endif
                </b>
            </label>
            <input class="form-control text-dark @error($field) is-invalid @enderror" id="{{ $field }}"
                name="{{ $field }}" type="text" value="{{ old($field, $address?->{$field}) }}" @required($required)>
            @error($field)
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endforeach

    <div class="col-12">
        <div class="form-check mb-2">
            <input class="form-check-input" id="is_default_shipping" name="is_default_shipping" type="checkbox"
                value="1" @checked(old('is_default_shipping', $address?->is_default_shipping ?? false))>
            <label class="form-check-label text-dark" for="is_default_shipping">
                {{ __('shop.account.addresses.make_default_shipping') }}
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" id="is_default_billing" name="is_default_billing" type="checkbox"
                value="1" @checked(old('is_default_billing', $address?->is_default_billing ?? false))>
            <label class="form-check-label text-dark" for="is_default_billing">
                {{ __('shop.account.addresses.make_default_billing') }}
            </label>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">{{ __('shop.account.addresses.save') }}</button>
    <a class="btn btn-danger" href="{{ route('customer.addresses.index') }}">
        {{ __('shop.account.addresses.cancel') }}
    </a>
</div>
