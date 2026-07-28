<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OrderItemOption extends Model
{
    protected $fillable = [
        'order_item_id',
        'attribute_code',
        'attribute_name',
        'option_code',
        'option_label',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Order item option snapshots are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Order item option snapshots cannot be deleted directly.');
        });
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
