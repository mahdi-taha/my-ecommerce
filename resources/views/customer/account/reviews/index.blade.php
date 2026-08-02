@extends('customer.account.layout')
@section('account-content')
<h1 class="h3 mb-4">{{ __('shop.reviews.my_reviews') }}</h1>
@forelse ($reviews as $review)
    <div class="card mb-3"><div class="card-body">
        <h2 class="h5">{{ $review->product->translations->first()?->name ?? $review->product->sku }}</h2>
        <p>{{ $review->rating }} ★ · {{ __('shop.reviews.status.'.$review->status->value) }}</p>
        <a class="btn btn-outline-primary" href="{{ route('shop.account.reviews.edit', ['review' => $review]) }}">{{ __('shop.reviews.edit') }}</a>
    </div></div>
@empty <p>{{ __('shop.reviews.empty_customer') }}</p> @endforelse
{{ $reviews->links() }}
@endsection
