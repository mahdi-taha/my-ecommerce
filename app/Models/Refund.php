<?php

namespace App\Models;

use App\Enums\ShippingTreatment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_number',
        'idempotency_key',
        'order_id',
        'order_payment_id',
        'currency_code',
        'merchandise_subtotal',
        'discount_amount',
        'tax_amount',
        'merchandise_amount',
        'return_shipping_cost',
        'shipping_treatment',
        'shipping_deduction',
        'company_shipping_loss',
        'customer_refund_amount',
        'reason',
        'customer_note',
        'internal_note',
        'created_by',
        'refunded_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Refund records are immutable.'));
        static::deleting(fn () => throw new LogicException('Refund records are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'shipping_treatment' => ShippingTreatment::class,
            'merchandise_subtotal' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'merchandise_amount' => 'decimal:4',
            'return_shipping_cost' => 'decimal:4',
            'shipping_deduction' => 'decimal:4',
            'company_shipping_loss' => 'decimal:4',
            'customer_refund_amount' => 'decimal:4',
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class, 'order_payment_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
