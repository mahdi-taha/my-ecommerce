<form method="GET" class="card card-body mb-4"><div class="row g-3">
<div class="col-md-3"><label class="form-label">From</label><input class="form-control" type="date" name="date_from" value="{{ request('date_from') }}"></div>
<div class="col-md-3"><label class="form-label">To</label><input class="form-control" type="date" name="date_to" value="{{ request('date_to') }}"></div>
<div class="col-md-2"><label class="form-label">Currency</label><input class="form-control" name="currency" maxlength="3" value="{{ request('currency') }}"></div>
<div class="col-md-2"><label class="form-label">Rows</label><select class="form-select" name="per_page">@foreach([25,50,100] as $size)<option @selected((int)request('per_page',25)===$size)>{{ $size }}</option>@endforeach</select></div>
<div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Apply</button></div>
<div class="col-md-2"><label class="form-label">Order Status</label><input class="form-control" name="order_status" value="{{ request('order_status') }}"></div>
<div class="col-md-2"><label class="form-label">Payment Status</label><input class="form-control" name="payment_status" value="{{ request('payment_status') }}"></div>
<div class="col-md-2"><label class="form-label">Fulfillment</label><input class="form-control" name="fulfillment_status" value="{{ request('fulfillment_status') }}"></div>
<div class="col-md-2"><label class="form-label">Payment Method</label><input class="form-control" name="payment_method" value="{{ request('payment_method') }}"></div>
<div class="col-md-2"><label class="form-label">Product ID</label><input class="form-control" type="number" name="product_id" value="{{ request('product_id') }}"></div>
<div class="col-md-2"><label class="form-label">Category ID</label><input class="form-control" type="number" name="category_id" value="{{ request('category_id') }}"></div>
<div class="col-md-2"><label class="form-label">Customer ID</label><input class="form-control" type="number" name="customer_id" value="{{ request('customer_id') }}"></div>
<div class="col-md-2"><label class="form-label">Administrator ID</label><input class="form-control" type="number" name="administrator_id" value="{{ request('administrator_id') }}"></div>
<div class="col-md-2"><label class="form-label">Shipping Treatment</label><input class="form-control" name="shipping_treatment" value="{{ request('shipping_treatment') }}"></div>
</div></form>
