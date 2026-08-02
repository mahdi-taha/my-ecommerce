<?php

namespace App\Models;

use App\Enums\ProductReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    protected $fillable = ['product_id', 'user_id', 'order_item_id', 'rating', 'title', 'review', 'status', 'admin_note', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'status' => ProductReviewStatus::class, 'reviewed_at' => 'datetime'];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ProductReviewStatus::Approved->value);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
