<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'parent_order_item_id',
        'product_id',
        'product_type',
        'sku',
        'product_number',
        'name',
        'option_summary',
        'image_path',
        'configuration',
        'quantity',
        'original_unit_price',
        'unit_price',
        'tax_name',
        'tax_rate',
        'tax_amount',
        'row_subtotal',
        'discount_amount',
        'row_total',
        'unit_cost',
        'is_inventory_item',
    ];

    protected static function booted(): void
    {
        static::updating(function (OrderItem $item): void {
            foreach (array_keys($item->getDirty()) as $field) {
                if (! in_array($field, ['unit_cost', 'updated_at'], true)) {
                    throw new LogicException("The Order item {$field} snapshot is immutable.");
                }
            }

            if (! $item->isDirty('unit_cost')) {
                return;
            }

            if ($item->getRawOriginal('unit_cost') !== null) {
                throw new LogicException('The Order item unit_cost snapshot has already been captured.');
            }

            if ($item->unit_cost === null) {
                throw new LogicException('The Order item unit_cost snapshot cannot be cleared.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'quantity' => 'decimal:4',
            'original_unit_price' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'row_subtotal' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'row_total' => 'decimal:4',
            'unit_cost' => 'decimal:4',
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

    public function review(): HasOne
    {
        return $this->hasOne(ProductReview::class);
    }

    public function refundItems(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }
}
