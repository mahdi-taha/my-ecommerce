<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\StoreProductReviewRequest;
use App\Models\Product;
use App\Services\ProductReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ProductReviewController extends Controller
{
    public function __construct(private ProductReviewService $reviews) {}

    public function store(StoreProductReviewRequest $request, Product $product): RedirectResponse
    {
        $key = 'product-review:'.$request->user('customer')->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['review' => __('shop.reviews.errors.throttled')]);
        }
        RateLimiter::hit($key, 60);
        $this->reviews->create($request->user('customer'), $product, $request->validated());

        return back()->with('success', __('shop.reviews.messages.submitted'));
    }
}
