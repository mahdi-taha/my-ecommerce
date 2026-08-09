<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateProductReviewRequest;
use App\Models\ProductReview;
use App\Services\ProductReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class ProductReviewController extends Controller
{
    public function __construct(private ProductReviewService $reviews) {}

    public function index(Request $request): JsonResponse|View
    {
        if ($request->ajax()) {
            $reviews = ProductReview::query()
                ->with([
                    'product.translations',
                    'customer:id,name,first_name,last_name,email',
                ])
                ->when(
                    $request->string('status')->isNotEmpty(),
                    fn ($query) => $query->where('status', $request->string('status'))
                );

            return DataTables::eloquent($reviews)
                ->filter(function ($query) use ($request): void {
                    $keyword = trim((string) $request->input('search.value'));

                    if ($keyword === '') {
                        return;
                    }

                    $query->where(function ($query) use ($keyword): void {
                        $query->where('title', 'like', "%{$keyword}%")
                            ->orWhere('review', 'like', "%{$keyword}%")
                            ->orWhereHas('product', fn ($query) => $query
                                ->where('sku', 'like', "%{$keyword}%")
                                ->orWhereHas('translations', fn ($query) => $query
                                    ->where('name', 'like', "%{$keyword}%")))
                            ->orWhereHas('customer', fn ($query) => $query
                                ->where('name', 'like', "%{$keyword}%")
                                ->orWhere('first_name', 'like', "%{$keyword}%")
                                ->orWhere('last_name', 'like', "%{$keyword}%")
                                ->orWhere('email', 'like', "%{$keyword}%"));
                    });
                })
                ->addColumn('product', fn (ProductReview $review) => e(
                    $review->product->translations->first()?->name ?? $review->product->sku
                ))
                ->addColumn('customer', fn (ProductReview $review) => e(
                    $review->customer->name ?: trim($review->customer->first_name.' '.$review->customer->last_name)
                ).'<br><small class="text-muted">'.e($review->customer->email).'</small>')
                ->editColumn('status', fn (ProductReview $review) => $this->statusBadge($review->status->value))
                ->editColumn('created_at', fn (ProductReview $review) => $review->created_at->format('Y-m-d H:i'))
                ->addColumn('action', fn (ProductReview $review) => '<a href="'.e(route('admin.reviews.show', $review)).'" class="btn btn-sm btn-outline-primary">Review</a>')
                ->rawColumns(['customer', 'status', 'action'])
                ->toJson();
        }

        return view('admin.reviews.index');
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

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            ProductReviewStatus::Approved->value => 'bg-success',
            ProductReviewStatus::Rejected->value => 'bg-danger',
            default => 'bg-warning text-dark',
        };

        return '<span class="badge '.$class.'">'.e(ucfirst($status)).'</span>';
    }
}
