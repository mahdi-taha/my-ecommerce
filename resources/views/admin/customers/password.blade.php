<x-admin-main page="Change Customer Password">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])
    </x-slot>

    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                        <div>
                            <h3 class="mb-1">Change Password</h3>
                            <p class="text-muted mb-0">{{ $customer->name }}</p>
                        </div>
                        <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-transparent">Back</a>
                    </div>

                    <div class="card shadow">
                        <div class="card-body">
                            <form action="{{ route('admin.customers.password.update', $customer) }}" method="POST"
                                autocomplete="off" onsubmit="disableSubmitButton(this)">
                                @csrf
                                @method('PUT')
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">New Password *</label>
                                        <input type="password" id="password" name="password"
                                            class="form-control @error('password') border-danger @enderror" required>
                                        @error('password')
                                            <p class="text-danger mt-1 mb-0">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="password_confirmation" class="form-label">Confirm Password *</label>
                                        <input type="password" id="password_confirmation" name="password_confirmation"
                                            class="form-control" required>
                                    </div>
                                    <div class="col-12 text-end">
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
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-main>
