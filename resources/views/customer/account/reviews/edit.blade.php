@extends('customer.account.layout')
@section('account-content')
    <div class="card shadow-sm">
        <div class="card-body">
            <h1 class="h3 mb-4">{{ __('shop.reviews.edit') }}</h1>
            <form method="POST" action="{{ route('shop.account.reviews.update', ['review' => $review]) }}">@csrf
                @method('PUT')
                <div class="mb-3">
                    <label for="rating" class="form-label text-dark"><b>{{ __('shop.reviews.rating') }}</b></label>
                    <select id="rating" name="rating" class="form-select text-dark">
                        @foreach (range(5, 1) as $rating)
                            <option value="{{ $rating }}" @selected(old('rating', $review->rating) == $rating)>{{ $rating }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="title"class="form-label text-dark">
                     <b>{{ __('shop.reviews.review_title') }}</b>
                    </label>
                    <input id="title" name="title" class="form-control text-dark" maxlength="150"
                        value="{{ old('title', $review->title) }}">
                </div>
                <div class="mb-3">
                    <label for="review" class="form-label text-dark">
                        <b>{{ __('shop.reviews.review') }}</b>
                    </label>
                    <textarea id="review" name="review" class="form-control text-dark" minlength="10" maxlength="5000" required>{{ old('review', $review->review) }}</textarea>
                </div>
                <button class="btn btn-primary">{{ __('shop.reviews.save') }}</button>
            </form>
        </div>
    </div>
@endsection
