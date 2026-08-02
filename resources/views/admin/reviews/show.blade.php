<x-admin-main page="Review Moderation">
<x-slot name="header">@vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])</x-slot>
<div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full" data-header-position="fixed"><x-admin-sidebar /><div class="body-wrapper"><x-admin-topbar /><div class="body-wrapper-inner"><div class="container-fluid">
<h1 class="h4">Review Moderation</h1><dl><dt>Product</dt><dd>{{ $review->product->translations->first()?->name ?? $review->product->sku }}</dd><dt>Customer</dt><dd>{{ $review->customer->name }}</dd><dt>Rating</dt><dd>{{ $review->rating }}</dd><dt>Review</dt><dd>{{ $review->review }}</dd></dl>
<form method="POST" action="{{ route('admin.reviews.update',$review) }}">@csrf @method('PATCH')<div class="mb-3"><label for="admin_note" class="form-label">Administrator note</label><textarea id="admin_note" name="admin_note" class="form-control" maxlength="2000">{{ old('admin_note',$review->admin_note) }}</textarea></div><button name="status" value="approved" class="btn btn-success">Approve</button> <button name="status" value="rejected" class="btn btn-danger">Reject</button></form>
</div></div></div></div>
</x-admin-main>
