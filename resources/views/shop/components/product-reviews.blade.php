<section class="mt-5" aria-labelledby="product-reviews-heading">
    <h2 id="product-reviews-heading" class="h4">{{ __('shop.reviews.title') }}</h2>
    @if (($product->approved_reviews_count ?? 0) > 0)
        <p>{{ __('shop.reviews.rating_summary', ['rating' => number_format((float) $product->approved_reviews_avg_rating, 1), 'count' => $product->approved_reviews_count]) }}</p>
        <div class="mb-4">
            @foreach (range(5, 1) as $rating)
                <div>{{ $rating }} ★ — {{ (int) ($ratingBreakdown[$rating] ?? 0) }}</div>
            @endforeach
        </div>
    @endif

    @auth('customer')
        @if ($customerReview)
            <div class="alert alert-info">{{ __('shop.reviews.existing_status', ['status' => __('shop.reviews.status.'.$customerReview->status->value)]) }}</div>
        @elseif ($canReview)
            <form method="POST" action="{{ route('shop.products.reviews.store', ['product' => $product]) }}" class="mb-4">
                @csrf
                <div class="mb-3"><label for="review-rating" class="form-label">{{ __('shop.reviews.rating') }}</label><select id="review-rating" name="rating" class="form-select" required>@foreach (range(5, 1) as $rating)<option value="{{ $rating }}">{{ $rating }}</option>@endforeach</select></div>
                <div class="mb-3"><label for="review-title" class="form-label">{{ __('shop.reviews.review_title') }}</label><input id="review-title" name="title" class="form-control" maxlength="150" value="{{ old('title') }}"></div>
                <div class="mb-3"><label for="review-body" class="form-label">{{ __('shop.reviews.review') }}</label><textarea id="review-body" name="review" class="form-control" minlength="10" maxlength="5000" required>{{ old('review') }}</textarea></div>
                <button class="btn btn-primary" type="submit">{{ __('shop.reviews.submit') }}</button>
            </form>
        @endif
    @endauth

    @forelse ($approvedReviews as $review)
        <article class="border-top py-3">
            <strong>{{ $review->customer->first_name }} {{ mb_substr((string) $review->customer->last_name, 0, 1) }}.</strong>
            <span class="text-warning">{{ str_repeat('★', $review->rating) }}</span>
            @if ($review->title)<h3 class="h6 mt-2">{{ $review->title }}</h3>@endif
            <p class="mb-1">{{ $review->review }}</p>
            <small class="text-muted">{{ $review->created_at->format('d-m-Y') }}</small>
        </article>
    @empty
        <p class="text-muted">{{ __('shop.reviews.empty') }}</p>
    @endforelse
    {{ $approvedReviews->withQueryString()->links('pagination::bootstrap-5') }}
</section>
