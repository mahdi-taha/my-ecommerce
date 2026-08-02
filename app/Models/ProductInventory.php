<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductInventory extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
        'average_cost',
        'low_stock_alert',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'average_cost' => 'decimal:4',
        'low_stock_alert' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function availableQuantity(): string
    {
        return $this->quantity;
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('quantity', '<=', 0);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query
            ->where('quantity', '>', 0)
            ->whereNotNull('low_stock_alert')
            ->whereColumn('quantity', '<=', 'low_stock_alert');
    }
}
