@php
    $isEdit = isset($homepageService);
    $translations = $isEdit ? $homepageService->translations->keyBy('locale') : collect();
@endphp

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h2 class="h5 mb-0">Service Information</h2>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="icon" class="form-label">Icon</label>
                <select id="icon" name="icon" class="form-select @error('icon') is-invalid @enderror" required>
                    @foreach (\App\Enums\HomepageServiceIcon::cases() as $icon)
                        <option value="{{ $icon->value }}" @selected(old('icon', $homepageService->icon->value ?? '') === $icon->value)>{{ $icon->label() }}</option>
                    @endforeach
                </select>
                @error('icon')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="sort_order" class="form-label">Sort Order</label>
                <input id="sort_order" name="sort_order" type="number" min="0" step="1"
                    class="form-control @error('sort_order') is-invalid @enderror"
                    value="{{ old('sort_order', $homepageService->sort_order ?? 0) }}" required>
                @error('sort_order')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4 d-flex align-items-center pt-md-4">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input id="is_active" name="is_active" value="1" type="checkbox"
                        class="form-check-input @error('is_active') is-invalid @enderror" @checked(old('is_active', $homepageService->is_active ?? false))>
                    <label for="is_active" class="form-check-label">Active</label>
                    @error('is_active')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>
</div>

@foreach (['en' => 'English Content', 'ar' => 'Arabic Content'] as $locale => $heading)
    @php($translation = $translations->get($locale))
    <div class="card shadow-sm mb-4" @if ($locale === 'ar') dir="rtl" @endif>
        <div class="card-header">
            <h2 class="h5 mb-0">{{ $heading }}</h2>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label for="title_{{ $locale }}" class="form-label">Title</label>
                <input id="title_{{ $locale }}" name="title_{{ $locale }}" type="text" maxlength="120"
                    class="form-control @error('title_' . $locale) is-invalid @enderror"
                    value="{{ old('title_' . $locale, $translation?->title) }}" required>
                @error('title_' . $locale)
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label for="description_{{ $locale }}" class="form-label">Description</label>
                <textarea id="description_{{ $locale }}" name="description_{{ $locale }}" rows="4" maxlength="500"
                    class="form-control @error('description_' . $locale) is-invalid @enderror" required>{{ old('description_' . $locale, $translation?->description) }}</textarea>
                @error('description_' . $locale)
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
@endforeach

<div class="card shadow-sm">
    <div class="card-body text-end">
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
