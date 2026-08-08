<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RefundItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_id',
        'order_item_id',
        'quantity',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'line_amount',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Refund item records are immutable.'));
        static::deleting(fn () => throw new LogicException('Refund item records are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'subtotal_amount' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_amount' => 'decimal:4',
        ];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
