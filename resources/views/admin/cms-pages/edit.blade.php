<x-admin-main page="Edit CMS Page">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])
    </x-slot>
    @php($translations = $cmsPage->translations->keyBy('locale'))
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid">

                    <div class="row">
                        <div class="col-6">
                            <h4> <b>Edit {{ $cmsPage->code }}</b> </h4>
                        </div>
                        <div class="col-6 text-end">
                            <a href="{{ route('admin.cms-pages.index') }}" class="btn btn-transparent">Back</a>
                        </div>
                    </div>
                    <hr />
                    <form method="POST" action="{{ route('admin.cms-pages.update', $cmsPage) }}"  onsubmit="disableSubmitButton(this)">
                        @csrf
                        @method('PUT')
                        <div class="card shadow">
                            <div class="card-body">

                                <div class="row px-3">
                                    <div class="col-md-4 mb-3">
                                        <label for="sort_order" class="form-label">Footer Order</label>
                                        <input id="sort_order" name="sort_order" type="number" min="0"
                                            class="form-control" value="{{ old('sort_order', $cmsPage->sort_order) }}">
                                    </div>
                                    <div class="col-md-4 mb-3 form-check mt-md-4 align-items-center d-flex">
                                        <input type="hidden" name="is_active" value="0">
                                        <input id="is_active" name="is_active" value="1" type="checkbox"
                                            class="form-check-input cursor-pointer" @checked(old('is_active', $cmsPage->is_active))>
                                        <label for="is_active"
                                            class="form-check-label ms-2 cursor-pointer">Published</label>
                                    </div>
                                </div>
                                @foreach (['en' => 'English', 'ar' => 'Arabic'] as $locale => $label)
                                    @php($translation = $translations->get($locale)) <fieldset class="border rounded p-3 mb-4"
                                        @if ($locale === 'ar') dir="rtl" @endif>
                                        <legend class="float-none w-auto px-2 h6">{{ $label }}</legend>
                                        @foreach (['title' => 'Title', 'slug' => 'Slug', 'meta_title' => 'Meta Title'] as $field => $fieldLabel)
                                            <div class="mb-3"><label for="{{ $field }}_{{ $locale }}"
                                                    class="form-label">{{ $fieldLabel }}</label><input
                                                    id="{{ $field }}_{{ $locale }}"
                                                    name="{{ $field }}_{{ $locale }}"
                                                    class="form-control @error($field . '_' . $locale) is-invalid @enderror"
                                                    value="{{ old($field . '_' . $locale, $translation?->{$field}) }}">
                                                @error($field . '_' . $locale)
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        @endforeach
                                        <div class="mb-3"><label for="body_{{ $locale }}"
                                                class="form-label">Body</label>
                                            <textarea id="body_{{ $locale }}" name="body_{{ $locale }}" rows="10"
                                                class="form-control @error('body_' . $locale) is-invalid @enderror">{{ old('body_' . $locale, $translation?->body) }}</textarea>
                                            @error('body_' . $locale)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="mb-3"><label for="meta_description_{{ $locale }}"
                                                class="form-label">Meta Description</label>
                                            <textarea id="meta_description_{{ $locale }}" name="meta_description_{{ $locale }}" class="form-control">{{ old('meta_description_' . $locale, $translation?->meta_description) }}</textarea>
                                        </div>
                                    </fieldset>
                                @endforeach
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary shadow">
                                <span class="btn-text">Save</span>
                                <span class="btn-loading d-none">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
</x-admin-main>
