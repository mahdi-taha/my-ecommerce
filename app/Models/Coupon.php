<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'is_active',
        'starts_at',
        'ends_at',
        'minimum_subtotal',
        'usage_limit',
        'per_customer_usage_limit',
        'is_first_order_only',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'decimal:4',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'minimum_subtotal' => 'decimal:4',
            'usage_limit' => 'integer',
            'per_customer_usage_limit' => 'integer',
            'is_first_order_only' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function unreleasedUsages(): HasMany
    {
        return $this->usages()->whereDoesntHave('release');
    }
}
