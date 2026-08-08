<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'admin_creation_key',
        'user_id',
        'customer_email',
        'customer_first_name',
        'customer_last_name',
        'customer_phone',
        'locale',
        'currency_code',
        'status',
        'payment_status',
        'fulfillment_status',
        'payment_method',
        'requires_payment_before_processing',
        'subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
        'grand_total',
        'customer_notes',
        'placed_at',
        'paid_at',
        'cancelled_at',
        'completed_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (Order $order): void {
            $mutableFields = [
                'status',
                'payment_status',
                'fulfillment_status',
                'paid_at',
                'cancelled_at',
                'completed_at',
                'updated_at',
            ];

            foreach (array_keys($order->getDirty()) as $field) {
                if (! in_array($field, $mutableFields, true)) {
                    throw new LogicException("The Order {$field} snapshot is immutable.");
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'requires_payment_before_processing' => 'boolean',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'shipping_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'placed_at' => 'datetime:Y-m-d H:i:s',
            'paid_at' => 'datetime:Y-m-d H:i:s',
            'cancelled_at' => 'datetime:Y-m-d H:i:s',
            'completed_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)
            ->where('type', 'billing');
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)
            ->where('type', 'shipping');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(OrderPayment::class);
    }

    public function shipping(): HasOne
    {
        return $this->hasOne(OrderShipping::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function couponUsage(): HasOne
    {
        return $this->hasOne(CouponUsage::class);
    }

    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(OrderCancellationRequest::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
