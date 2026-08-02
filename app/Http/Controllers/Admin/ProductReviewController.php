<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateProductReviewRequest;
use App\Models\ProductReview;
use App\Services\ProductReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function __construct(private ProductReviewService $reviews) {}

    public function index(Request $request): View
    {
        $reviews = ProductReview::query()->with(['product.translations', 'customer'])
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.reviews.index', compact('reviews'));
    }

    public function show(ProductReview $review): View
    {
        $review->load(['product.translations', 'customer', 'orderItem.order', 'reviewer']);

        return view('admin.reviews.show', compact('review'));
    }

    public function update(ModerateProductReviewRequest $request, ProductReview $review): RedirectResponse
    {
        $this->reviews->moderate($review, ProductReviewStatus::from($request->validated('status')), $request->validated('admin_note'), $request->user('admin'));

        return redirect()->route('admin.reviews.show', $review)->with('success', 'Review moderation saved.');
    }
}
