<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductType;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductReviewService
{
    public function create(User $customer, Product $product, array $data): ProductReview
    {
        return DB::transaction(function () use ($customer, $product, $data): ProductReview {
            Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (ProductReview::query()->where('user_id', $customer->id)->where('product_id', $product->id)->exists()) {
                throw ValidationException::withMessages(['review' => __('shop.reviews.errors.duplicate')]);
            }

            $item = $this->newestQualifyingItem($customer, $product);
            if (! $item) {
                throw ValidationException::withMessages(['review' => __('shop.reviews.errors.not_eligible')]);
            }

            return ProductReview::create([
                'product_id' => $product->id, 'user_id' => $customer->id,
                'order_item_id' => $item->id, 'rating' => $data['rating'],
                'title' => $this->nullableTrim($data['title'] ?? null), 'review' => trim($data['review']),
                'status' => ProductReviewStatus::Pending,
            ]);
        });
    }

    public function update(User $customer, ProductReview $review, array $data): ProductReview
    {
        abort_unless((int) $review->user_id === (int) $customer->id, 404);

        $review->update([
            'rating' => $data['rating'], 'title' => $this->nullableTrim($data['title'] ?? null),
            'review' => trim($data['review']), 'status' => ProductReviewStatus::Pending,
            'admin_note' => null, 'reviewed_by' => null, 'reviewed_at' => null,
        ]);

        return $review->refresh();
    }

    public function moderate(ProductReview $review, ProductReviewStatus $status, ?string $note, User $admin): ProductReview
    {
        if ($status === ProductReviewStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'A moderation decision is required.']);
        }

        $review->update(['status' => $status, 'admin_note' => $this->nullableTrim($note), 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);

        return $review->refresh();
    }

    public function newestQualifyingItem(User $customer, Product $product): ?OrderItem
    {
        if ($product->configurable_id !== null) {
            return null;
        }

        return OrderItem::query()
            ->select('order_items.*')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.user_id', $customer->id)
            ->where('orders.status', OrderStatus::Completed->value)
            ->where(function ($query) use ($product): void {
                if ($product->type === ProductType::Configurable->value) {
                    $query->whereHas('product', fn ($query) => $query->where('configurable_id', $product->id));
                } else {
                    $query->where('order_items.product_id', $product->id);
                }
            })
            ->orderByDesc('orders.placed_at')->orderByDesc('orders.id')->orderByDesc('order_items.id')
            ->first();
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
