<?php

namespace App\Http\Controllers\Shop\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\UpdateProductReviewRequest;
use App\Models\ProductReview;
use App\Services\ProductReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function __construct(private ProductReviewService $reviews) {}

    public function index(Request $request): View
    {
        $reviews = ProductReview::query()->where('user_id', $request->user('customer')->id)
            ->with(['product.translations' => fn ($query) => $query->where('locale', app()->getLocale())])
            ->latest()->paginate(10);

        return view('customer.account.reviews.index', compact('reviews'));
    }

    public function edit(Request $request, ProductReview $review): View
    {
        abort_unless((int) $review->user_id === (int) $request->user('customer')->id, 404);
        $review->load(['product.translations' => fn ($query) => $query->where('locale', app()->getLocale())]);

        return view('customer.account.reviews.edit', compact('review'));
    }

    public function update(UpdateProductReviewRequest $request, ProductReview $review): RedirectResponse
    {
        $key = 'product-review:'.$request->user('customer')->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['review' => __('shop.reviews.errors.throttled')]);
        }
        RateLimiter::hit($key, 60);
        $this->reviews->update($request->user('customer'), $review, $request->validated());

        return redirect()->route('shop.account.reviews.index')->with('success', __('shop.reviews.messages.updated'));
    }
}
