<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class CouponUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'order_id',
        'user_id',
        'coupon_code',
        'coupon_type',
        'coupon_value',
        'eligible_subtotal',
        'discount_amount',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Coupon usage records are immutable.'));
        static::deleting(fn () => throw new LogicException('Coupon usage records are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'coupon_type' => CouponType::class,
            'coupon_value' => 'decimal:4',
            'eligible_subtotal' => 'decimal:4',
            'discount_amount' => 'decimal:4',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function release(): HasOne
    {
        return $this->hasOne(CouponUsageRelease::class);
    }
}
