<form method="GET" class="card card-body mb-4"><div class="row g-3">
<div class="col-md-3"><label class="form-label">From</label><input class="form-control" type="date" name="date_from" value="{{ request('date_from') }}"></div>
<div class="col-md-3"><label class="form-label">To</label><input class="form-control" type="date" name="date_to" value="{{ request('date_to') }}"></div>
<div class="col-md-2"><label class="form-label">Currency</label><input class="form-control" name="currency" maxlength="3" value="{{ request('currency') }}"></div>
<div class="col-md-2"><label class="form-label">Rows</label><select class="form-select" name="per_page">@foreach([25,50,100] as $size)<option @selected((int)request('per_page',25)===$size)>{{ $size }}</option>@endforeach</select></div>
<div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Apply</button></div>
</div></form>
