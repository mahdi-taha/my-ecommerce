<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OrderShipping extends Model
{
    protected $table = 'order_shipping';

    protected $fillable = [
        'order_id',
        'shipping_method_id',
        'shipping_method_code',
        'shipping_method_name',
        'shipping_method_type',
        'shipping_amount',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Order shipping snapshots are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Order shipping snapshots cannot be deleted directly.');
        });
    }

    protected function casts(): array
    {
        return [
            'shipping_amount' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }
}
