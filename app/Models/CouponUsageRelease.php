<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CouponUsageRelease extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_usage_id',
        'reason',
        'released_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Coupon usage releases are immutable.'));
        static::deleting(fn () => throw new LogicException('Coupon usage releases are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
        ];
    }

    public function usage(): BelongsTo
    {
        return $this->belongsTo(CouponUsage::class, 'coupon_usage_id');
    }
}
