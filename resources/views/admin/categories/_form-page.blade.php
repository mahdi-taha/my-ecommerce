@php
    $isEdit = isset($category);
    $pageTitle = $isEdit ? 'Edit Category' : 'Create Category';
    $heading = $isEdit ? 'Edit Category' : 'Add Category';
    $englishTranslation = $isEdit ? $category->translations->firstWhere('locale', 'en') : null;
    $arabicTranslation = $isEdit ? $category->translations->firstWhere('locale', 'ar') : null;
    $currentAttributeIds = $isEdit ? $category->filterableAttributes->pluck('id')->all() : [];
    $selectedAttributeIds = old('_category_form_submitted')
        ? (array) old('filterable_attributes', [])
        : $currentAttributeIds;
    $logoUrl = $isEdit && $category->logo_path ? asset('storage/' . $category->logo_path) : '';
    $bannerUrl = $isEdit && $category->banner_path ? asset('storage/' . $category->banner_path) : '';
@endphp

<x-admin-main :page="$pageTitle">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/categories.js'])
    </x-slot>

    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />

        <div class="body-wrapper">
            <x-admin-topbar />
            @php
                $slugArabicErrors = $errors->hasAny(['category_name_ar', 'category_slug_ar']);
                $metaArabicErrors = $errors->hasAny(['meta_title_ar', 'meta_description_ar', 'meta_keywords_ar']);

                $slugEnglishErrors = $errors->hasAny(['category_name_en', 'category_slug_en']);
                $metaEnglishErrors = $errors->hasAny(['meta_title_en', 'meta_description_en', 'meta_keywords_en']);
            @endphp
            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-6">
                            <h4><b>{{ $heading }}</b></h4>
                        </div>
                        <div class="col-6 text-end">
                            <a href="{{ route('admin.categories.index') }}" class="btn btn-transparent">Back</a>
                        </div>
                    </div>

                    <hr>

                    <form
                        action="{{ $isEdit ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
                        method="POST" enctype="multipart/form-data" autocomplete="off"
                        onsubmit="disableSubmitButton(this)">
                        @csrf
                        <input type="hidden" name="_category_form_submitted" value="1">
                        @if ($isEdit)
                            @method('PUT')
                        @endif

                        <div class="row">
                            <div class="col-lg-8">
                                <div class="row inputs-container mt-3 pb-3 px-2 rounded shadow"
                                    style="border: 1px solid #d4d9e4;">
                                    <h5 class="mb-4 mt-3">General</h5>

                                    <div class="col-12">
                                        <div
                                            class="mb-3 mt-1 select2-div {{ $errors->has('parent_id') ? 'select2-danger' : '' }}">
                                            <label for="parent_id" class="form-label">Parent Category</label>
                                            <select
                                                class="form-select category-parent-select select2 @error('parent_id') border-danger @enderror"
                                                id="parent_id" name="parent_id"
                                                data-placeholder="Select Parent Category"
                                                data-has-error="{{ $errors->has('parent_id') ? 'true' : 'false' }}">
                                                <option value=""></option>
                                                @foreach ($categories as $parentCategory)
                                                    <option value="{{ $parentCategory->id }}"
                                                        @selected(old('parent_id', $isEdit ? $category->parent_id : null) == $parentCategory->id)>
                                                        {{ $parentCategory->translations->first()?->name ?? '-' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('parent_id')
                                                <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <ul class="nav nav-tabs" id="category-general-tabs" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link active {{ $slugEnglishErrors ? 'text-danger' : '' }}" id="general-en-tab" data-bs-toggle="tab"
                                                    data-bs-target="#general-en" type="button" role="tab"
                                                    aria-controls="general-en" aria-selected="true">
                                                    English
                                                </button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link {{ $slugArabicErrors ? 'text-danger' : '' }}" id="general-ar-tab" data-bs-toggle="tab"
                                                    data-bs-target="#general-ar" type="button" role="tab"
                                                    aria-controls="general-ar" aria-selected="false">
                                                    Arabic
                                                </button>
                                            </li>
                                        </ul>

                                        <div class="tab-content pt-3" id="category-general-tabs-content">
                                            <div class="tab-pane fade show active" id="general-en" role="tabpanel"
                                                aria-labelledby="general-en-tab" tabindex="0">
                                                <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="mb-3">
                                                            <label for="category_name_en" class="form-label">Name
                                                                *</label>
                                                            <input type="text"
                                                                class="form-control category-name @error('category_name_en') border-danger @enderror"
                                                                id="category_name_en" name="category_name_en"
                                                                value="{{ old('category_name_en', $englishTranslation?->name) }}"
                                                                data-locale="en" required>
                                                            @error('category_name_en')
                                                                <p class="text-danger">{{ $message }}</p>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="col-lg-6">
                                                        <div class="mb-3">
                                                            <label for="category_slug_en" class="form-label">Slug
                                                                *</label>
                                                            <input type="text"
                                                                class="form-control category-slug @error('category_slug_en') border-danger @enderror"
                                                                id="category_slug_en" name="category_slug_en"
                                                                value="{{ old('category_slug_en', $englishTranslation?->slug) }}"
                                                                data-locale="en" required>
                                                            @error('category_slug_en')
                                                                <p class="text-danger">{{ $message }}</p>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="tab-pane fade" id="general-ar" role="tabpanel"
                                                aria-labelledby="general-ar-tab" tabindex="0">
                                                <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="mb-3">
                                                            <label for="category_name_ar" class="form-label">Name
                                                                *</label>
                                                            <input type="text"
                                                                class="form-control category-name @error('category_name_ar') border-danger @enderror"
                                                                id="category_name_ar" name="category_name_ar"
                                                                value="{{ old('category_name_ar', $arabicTranslation?->name) }}"
                                                                data-locale="ar" dir="rtl" required>
                                                            @error('category_name_ar')
                                                                <p class="text-danger">{{ $message }}</p>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="col-lg-6">
                                                        <div class="mb-3">
                                                            <label for="category_slug_ar" class="form-label">Slug
                                                                *</label>
                                                            <input type="text"
                                                                class="form-control category-slug @error('category_slug_ar') border-danger @enderror"
                                                                id="category_slug_ar" name="category_slug_ar"
                                                                value="{{ old('category_slug_ar', $arabicTranslation?->slug) }}"
                                                                data-locale="ar" dir="rtl" required>
                                                            @error('category_slug_ar')
                                                                <p class="text-danger">{{ $message }}</p>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-3 inputs-container shadow pb-3 px-2 rounded"
                                    style="border: 1px solid #d4d9e4;">
                                    <h5 class="mb-4 mt-3">Images</h5>

                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="logo" class="form-label">Logo</label>
                                            <div
                                                class="custom-image-upload border rounded p-3 text-center @error('logo') border-danger @enderror">
                                                <img id="logo-preview" src="{{ $logoUrl }}" alt="Logo preview"
                                                    class="img-fluid rounded mb-2 mx-auto {{ $logoUrl ? '' : 'd-none' }}"
                                                    data-existing-src="{{ $logoUrl }}"
                                                    style="max-height: 150px; object-fit: contain;">

                                                <label class="btn btn-outline-primary btn-sm">
                                                    Choose Logo
                                                    <input type="file" name="logo" id="logo"
                                                        accept="image/*" class="d-none category-image-input"
                                                        data-preview="logo-preview" data-remove="remove-logo">
                                                </label>
                                                <button type="button" id="remove-logo"
                                                    class="btn btn-dark btn-sm d-none category-image-remove"
                                                    data-input="logo" data-preview="logo-preview">
                                                    Remove
                                                </button>
                                            </div>
                                            @error('logo')
                                                <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="banner" class="form-label">Banner</label>
                                            <div
                                                class="custom-image-upload border rounded p-3 text-center @error('banner') border-danger @enderror">
                                                <img id="banner-preview" src="{{ $bannerUrl }}"
                                                    alt="Banner preview"
                                                    class="img-fluid rounded mb-2 mx-auto {{ $bannerUrl ? '' : 'd-none' }}"
                                                    data-existing-src="{{ $bannerUrl }}"
                                                    style="max-height: 150px; object-fit: contain;">

                                                <label class="btn btn-outline-primary btn-sm">
                                                    Choose Banner
                                                    <input type="file" name="banner" id="banner"
                                                        accept="image/*" class="d-none category-image-input"
                                                        data-preview="banner-preview" data-remove="remove-banner">
                                                </label>
                                                <button type="button" id="remove-banner"
                                                    class="btn btn-dark btn-sm d-none category-image-remove"
                                                    data-input="banner" data-preview="banner-preview">
                                                    Remove
                                                </button>
                                            </div>
                                            @error('banner')
                                                <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row inputs-container shadow mt-3 pb-3 px-2 rounded"
                                    style="border: 1px solid #d4d9e4;">
                                    <h5 class="mb-4 mt-3">SEO Details</h5>

                                    <div class="col-12">
                                        <ul class="nav nav-tabs" id="category-seo-tabs" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link active {{ $metaEnglishErrors ? 'text-danger' : '' }}" id="seo-en-tab" data-bs-toggle="tab"
                                                    data-bs-target="#seo-en" type="button" role="tab"
                                                    aria-controls="seo-en" aria-selected="true">
                                                    English
                                                </button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link {{ $metaArabicErrors ? 'text-danger' : '' }}" id="seo-ar-tab" data-bs-toggle="tab"
                                                    data-bs-target="#seo-ar" type="button" role="tab"
                                                    aria-controls="seo-ar" aria-selected="false">
                                                    Arabic
                                                </button>
                                            </li>
                                        </ul>

                                        <div class="tab-content pt-3" id="category-seo-tabs-content">
                                            <div class="tab-pane fade show active" id="seo-en" role="tabpanel"
                                                aria-labelledby="seo-en-tab" tabindex="0">
                                                <div class="mb-3">
                                                    <label for="meta_title_en" class="form-label">Meta Title</label>
                                                    <input type="text"
                                                        class="form-control @error('meta_title_en') border-danger @enderror"
                                                        id="meta_title_en" name="meta_title_en"
                                                        value="{{ old('meta_title_en', $englishTranslation?->meta_title) }}">
                                                    @error('meta_title_en')
                                                        <p class="text-danger">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div class="mb-3">
                                                    <label for="meta_keywords_en" class="form-label">Meta
                                                        Keywords</label>
                                                    <input type="text"
                                                        class="form-control @error('meta_keywords_en') border-danger @enderror"
                                                        id="meta_keywords_en" name="meta_keywords_en"
                                                        value="{{ old('meta_keywords_en', $englishTranslation?->meta_keywords) }}">
                                                    @error('meta_keywords_en')
                                                        <p class="text-danger">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div class="mb-3">
                                                    <label for="meta_description_en" class="form-label">Meta
                                                        Description</label>
                                                    <textarea class="form-control @error('meta_description_en') border-danger @enderror" id="meta_description_en"
                                                        name="meta_description_en" rows="4">{{ old('meta_description_en', $englishTranslation?->meta_description) }}</textarea>
                                                    @error('meta_description_en')
                                                        <p class="text-danger">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="tab-pane fade" id="seo-ar" role="tabpanel"
                                                aria-labelledby="seo-ar-tab" tabindex="0">
                                                <div class="mb-3">
                                                    <label for="meta_title_ar" class="form-label">Meta Title</label>
                                                    <input type="text"
                                                        class="form-control @error('meta_title_ar') border-danger @enderror"
                                                        id="meta_title_ar" name="meta_title_ar"
                                                        value="{{ old('meta_title_ar', $arabicTranslation?->meta_title) }}"
                                                        dir="rtl">
                                                    @error('meta_title_ar')
                                                        <p class="text-danger">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div class="mb-3">
                                                    <label for="meta_keywords_ar" class="form-label">Meta
                                                        Keywords</label>
                                                    <input type="text"
                                                        class="form-control @error('meta_keywords_ar') border-danger @enderror"
                                                        id="meta_keywords_ar" name="meta_keywords_ar"
                                                        value="{{ old('meta_keywords_ar', $arabicTranslation?->meta_keywords) }}"
                                                        dir="rtl">
                                                    @error('meta_keywords_ar')
                                                        <p class="text-danger">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div class="mb-3">
                                                    <label for="meta_description_ar" class="form-label">Meta
                                                        Description</label>
                                                    <textarea class="form-control @error('meta_description_ar') border-danger @enderror" id="meta_description_ar"
                                                        name="meta_description_ar" rows="4" dir="rtl">{{ old('meta_description_ar', $arabicTranslation?->meta_description) }}</textarea>
                                                    @error('meta_description_ar')
                                                        <p class="text-danger">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4 px-lg-4">
                                <div class="row inputs-container shadow mt-3 pb-3 px-2 rounded"
                                    style="border: 1px solid #d4d9e4;">
                                    <h5 class="mb-4 mt-3">Settings</h5>

                                    <div class="col-12">
                                        <div class="mb-3 mt-1">
                                            <label for="position" class="form-label">Position *</label>
                                            <input type="number" min="0" step="1"
                                                class="form-control @error('position') border-danger @enderror"
                                                id="position" name="position"
                                                value="{{ old('position', $isEdit ? $category->position : 0) }}"
                                                required>
                                            @error('position')
                                                <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <input type="hidden" name="status" value="0">
                                        <div class="form-check form-switch">
                                            <input
                                                class="form-check-input cursor-pointer @error('status') border-danger @enderror"
                                                type="checkbox" id="status" name="status" value="1"
                                                @checked(old('status', $isEdit ? $category->status : 0))>
                                            <label class="form-check-label cursor-pointer"
                                                for="status">Active</label>
                                        </div>
                                        @error('status')
                                            <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row inputs-container shadow mt-3 pb-3 px-2 rounded"
                                    style="border: 1px solid #d4d9e4;">
                                    <h5 class="mb-4 mt-3">Filterable Attributes</h5>

                                    <div class="col-12">
                                        @if ($attributes->isEmpty())
                                            <h6 class="mb-4 mt-3">No Attributes</h6>
                                        @else
                                            @foreach ($attributes as $attribute)
                                                @php
                                                    $attributeName =
                                                        $attribute->translations->first()?->admin_name ?? '-';
                                                @endphp
                                                <div class="form-check mb-2">
                                                    <input
                                                        class="form-check-input cursor-pointer @error('filterable_attributes.' . $loop->index) border-danger @enderror"
                                                        type="checkbox" name="filterable_attributes[]"
                                                        value="{{ $attribute->id }}"
                                                        id="filterable-attribute-{{ $attribute->id }}"
                                                        @checked(in_array($attribute->id, old('filterable_attributes', $selectedAttributeIds)))>
                                                    <label class="form-check-label cursor-pointer"
                                                        for="filterable-attribute-{{ $attribute->id }}">
                                                        {{ $attributeName }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        @endif

                                        @if ($errors->has('filterable_attributes') || $errors->has('filterable_attributes.*'))
                                            <div class="text-danger">
                                                @foreach ($errors->get('filterable_attributes') as $message)
                                                    <p class="mb-1">{{ $message }}</p>
                                                @endforeach
                                                @foreach ($errors->get('filterable_attributes.*') as $messages)
                                                    @foreach ($messages as $message)
                                                        <p class="mb-1">{{ $message }}</p>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <hr class="mt-4">

                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary shadow">
                                    <span class="btn-text">{{ $isEdit ? 'Update Category' : 'Save' }}</span>
                                    <span class="btn-loading d-none">Saving...</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</x-admin-main>
