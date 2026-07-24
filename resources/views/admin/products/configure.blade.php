<x-admin-main page="Configure Product">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js', 'resources/js/admin/products.js'])
    </x-slot>

    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />

        <div class="body-wrapper">
            <x-admin-topbar />

            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h4><b>Configure Product</b></h4>
                            <p class="text-muted mb-0">{{ $product->sku }}</p>
                        </div>
                        <div class="col-4 text-end">
                            <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-transparent">Back</a>
                        </div>
                    </div>

                    <hr>

                    <form action="{{ route('admin.products.configure.store', $product) }}" method="POST"
                        id="configurable-product-form" onsubmit="disableSubmitButton(this)">
                        @csrf

                        @error('selected_attributes')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror
                        @error('super_attributes')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror

                        <div class="inputs-container shadow mt-3 pb-3 px-3 rounded" style="border: 1px solid #d4d9e4;">
                            <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
                                <h5 class="mb-0">Configurable Attributes</h5>
                                <span class="badge bg-primary fs-3">
                                    Combinations: <span id="combination-count">0</span>
                                </span>
                            </div>

                            @forelse ($attributes as $attribute)
                                @php
                                    $label = $attribute->translations->first()?->admin_name ?? $attribute->code;
                                    $oldSelectedAttributes = array_map('strval', old('selected_attributes', []));
                                    $oldOptions = array_map('strval', old('super_attributes.'.$attribute->id, []));
                                @endphp
                                <div class="border rounded p-3 mb-3 configurable-attribute" data-attribute-id="{{ $attribute->id }}">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input configurable-attribute-checkbox" type="checkbox"
                                            id="selected_attribute_{{ $attribute->id }}" name="selected_attributes[]"
                                            value="{{ $attribute->id }}" @checked(in_array((string) $attribute->id, $oldSelectedAttributes, true))
                                            @required($attribute->is_required)>
                                        <label class="form-check-label fw-semibold" for="selected_attribute_{{ $attribute->id }}">
                                            {{ $label }}{{ $attribute->is_required ? ' *' : '' }}
                                        </label>
                                    </div>

                                    <div class="row g-2 configurable-options">
                                        @forelse ($attribute->options as $option)
                                            <div class="col-md-4 col-lg-3">
                                                <div class="form-check">
                                                    <input class="form-check-input configurable-option-checkbox" type="checkbox"
                                                        id="configurable_option_{{ $option->id }}"
                                                        name="super_attributes[{{ $attribute->id }}][]"
                                                        value="{{ $option->id }}"
                                                        @checked(in_array((string) $option->id, $oldOptions, true))>
                                                    <label class="form-check-label" for="configurable_option_{{ $option->id }}">
                                                        {{ $option->translations->first()?->label ?? 'Option #'.$option->id }}
                                                    </label>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="col-12 text-muted">No options available.</div>
                                        @endforelse
                                    </div>

                                    @error('super_attributes.'.$attribute->id)
                                        <p class="text-danger mt-2 mb-0">{{ $message }}</p>
                                    @enderror
                                </div>
                            @empty
                                <div class="alert alert-info mb-0">No active configurable select attributes are available.</div>
                            @endforelse
                        </div>

                        @if ($attributes->isNotEmpty())
                            <div id="combination-limit-error" class="alert alert-danger mt-3 d-none">
                                A configurable product cannot exceed 200 combinations.
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary shadow" id="generate-variants-button">
                                    <span class="btn-text">Generate Variants</span>
                                    <span class="btn-loading d-none">Generating...</span>
                                </button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
