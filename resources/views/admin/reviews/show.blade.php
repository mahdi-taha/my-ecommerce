<x-admin-main page="Review Moderation">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])
    </x-slot>

    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6"
        data-sidebartype="full" data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner"><div class="container-fluid py-4">
                <h1 class="h4 mb-4">Review Moderation</h1>

                <section class="card shadow-sm mb-4" aria-labelledby="review-details-heading">
                    <div class="card-header"><h2 id="review-details-heading" class="h5 mb-0">Review Details</h2></div>
                    <div class="card-body"><dl class="mb-0"><dt>Rating</dt><dd>{{ $review->rating }} / 5</dd><dt>Title</dt><dd>{{ $review->title ?: '—' }}</dd><dt>Review</dt><dd>{{ $review->review }}</dd><dt>Status</dt><dd>{{ ucfirst($review->status->value) }}</dd></dl></div>
                </section>

                <section class="card shadow-sm mb-4" aria-labelledby="review-evidence-heading">
                    <div class="card-header"><h2 id="review-evidence-heading" class="h5 mb-0">Product, Customer, and Purchase Evidence</h2></div>
                    <div class="card-body"><dl class="mb-0"><dt>Product</dt><dd>{{ $review->product->translations->first()?->name ?? $review->product->sku }}</dd><dt>Customer</dt><dd>{{ $review->customer->name }} ({{ $review->customer->email }})</dd><dt>Order</dt><dd>{{ $review->orderItem?->order?->order_number ?? 'Evidence order no longer available' }}</dd></dl></div>
                </section>

                <section class="card shadow-sm" aria-labelledby="review-moderation-heading">
                    <div class="card-header"><h2 id="review-moderation-heading" class="h5 mb-0">Moderation Actions</h2></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.reviews.update', $review) }}">
                            @csrf @method('PATCH')
                            <div class="mb-3">
                                <label for="admin_note" class="form-label">Administrator note</label>
                                <textarea id="admin_note" name="admin_note" maxlength="2000"
                                    class="form-control @error('admin_note') is-invalid @enderror">{{ old('admin_note', $review->admin_note) }}</textarea>
                                @error('admin_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button name="status" value="approved" class="btn btn-success">Approve</button>
                            <button name="status" value="rejected" class="btn btn-danger">Reject</button>
                        </form>
                    </div>
                </section>
            </div></div>
        </div>
    </div>
</x-admin-main>
