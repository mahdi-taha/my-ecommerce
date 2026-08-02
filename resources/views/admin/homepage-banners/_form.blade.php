@php
    $isEdit = isset($homepageBanner);
    $translations = $isEdit ? $homepageBanner->translations->keyBy('locale') : collect();
    $hasCurrentImage = $isEdit
        && filled($homepageBanner->image_path)
        && \Illuminate\Support\Facades\Storage::disk('public')->exists($homepageBanner->image_path);
@endphp

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">Content Information</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="placement" class="form-label">Placement</label>
                <select id="placement" name="placement"
                    class="form-select @error('placement') is-invalid @enderror" required>
                    @foreach (\App\Enums\HomepageBannerPlacement::cases() as $placement)
                        <option value="{{ $placement->value }}" @selected(old('placement', $homepageBanner->placement->value ?? '') === $placement->value)>
                            {{ str($placement->value)->replace('_', ' ')->title() }}
                        </option>
                    @endforeach
                </select>
                @error('placement')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="sort_order" class="form-label">Sort Order</label>
                <input id="sort_order" name="sort_order" type="number" min="0" step="1"
                    class="form-control @error('sort_order') is-invalid @enderror"
                    value="{{ old('sort_order', $homepageBanner->sort_order ?? 0) }}" required>
                @error('sort_order')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4 d-flex align-items-center pt-md-4">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input id="is_active" name="is_active" value="1" type="checkbox"
                        class="form-check-input @error('is_active') is-invalid @enderror"
                        @checked(old('is_active', $homepageBanner->is_active ?? false))>
                    <label for="is_active" class="form-check-label">Active</label>
                    @error('is_active')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>
</div>

@foreach (['en' => 'English Content', 'ar' => 'Arabic Content'] as $locale => $sectionTitle)
    @php($translation = $translations->get($locale))
    <div class="card shadow-sm mb-4" @if ($locale === 'ar') dir="rtl" @endif>
        <div class="card-header">
            <h5 class="mb-0">{{ $sectionTitle }}</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @foreach (['eyebrow' => 'Eyebrow', 'title' => 'Title', 'button_label' => 'Button Label', 'link_url' => 'Link URL', 'image_alt' => 'Image Alt Text'] as $field => $fieldLabel)
                    <div class="{{ in_array($field, ['title', 'link_url'], true) ? 'col-12' : 'col-md-6' }}">
                        <label for="{{ $field }}_{{ $locale }}" class="form-label">{{ $fieldLabel }}</label>
                        <input id="{{ $field }}_{{ $locale }}" name="{{ $field }}_{{ $locale }}" type="text"
                            class="form-control @error($field . '_' . $locale) is-invalid @enderror"
                            value="{{ old($field . '_' . $locale, $translation?->{$field}) }}"
                            @required($field === 'title')>
                        @error($field . '_' . $locale)
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @endforeach

                <div class="col-12">
                    <label for="body_{{ $locale }}" class="form-label">Body</label>
                    <textarea id="body_{{ $locale }}" name="body_{{ $locale }}" rows="4"
                        class="form-control @error('body_' . $locale) is-invalid @enderror">{{ old('body_' . $locale, $translation?->body) }}</textarea>
                    @error('body_' . $locale)
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>
@endforeach

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">Image</h5>
    </div>
    <div class="card-body">
        @if ($hasCurrentImage)
            <div class="mb-3">
                <p class="form-label">Current Image</p>
                <img src="{{ asset('storage/' . $homepageBanner->image_path) }}"
                    alt="{{ $translations->get('en')?->image_alt ?: 'Current homepage content image' }}"
                    class="img-fluid rounded border" style="max-height: 240px;">
            </div>
        @endif

        <label for="image" class="form-label">{{ $isEdit ? 'Replace Image' : 'Image' }}</label>
        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
            class="form-control @error('image') is-invalid @enderror">
        @error('image')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">JPG, JPEG, PNG, or WebP. Maximum size: 5 MB. Active content requires a valid image.</div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">Actions</h5>
    </div>
    <div class="card-body text-end">
        <a href="{{ route('admin.homepage-banners.index') }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update Homepage Content' : 'Create Homepage Content' }}
        </button>
    </div>
</div>
