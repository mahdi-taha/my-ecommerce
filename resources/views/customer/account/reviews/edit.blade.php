@extends('customer.account.layout')
@section('account-content')
<h1 class="h3 mb-4">{{ __('shop.reviews.edit') }}</h1>
<form method="POST" action="{{ route('shop.account.reviews.update', ['review' => $review]) }}">@csrf @method('PUT')
    <div class="mb-3"><label for="rating" class="form-label">{{ __('shop.reviews.rating') }}</label><select id="rating" name="rating" class="form-select">@foreach(range(5,1) as $rating)<option value="{{ $rating }}" @selected(old('rating', $review->rating)==$rating)>{{ $rating }}</option>@endforeach</select></div>
    <div class="mb-3"><label for="title" class="form-label">{{ __('shop.reviews.review_title') }}</label><input id="title" name="title" class="form-control" maxlength="150" value="{{ old('title', $review->title) }}"></div>
    <div class="mb-3"><label for="review" class="form-label">{{ __('shop.reviews.review') }}</label><textarea id="review" name="review" class="form-control" minlength="10" maxlength="5000" required>{{ old('review', $review->review) }}</textarea></div>
    <button class="btn btn-primary">{{ __('shop.reviews.save') }}</button>
</form>
@endsection
