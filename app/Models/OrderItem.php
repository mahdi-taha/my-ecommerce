<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class OrderItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (OrderItem $item): void {
            foreach (['tax_name', 'tax_rate', 'tax_amount'] as $field) {
                if ($item->isDirty($field)) {
                    throw new LogicException("The Order item {$field} snapshot is immutable.");
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'parent_order_item_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'parent_order_item_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class);
    }
}
