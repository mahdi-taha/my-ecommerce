<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class OrderPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_number',
        'order_id',
        'payment_method_id',
        'method_code',
        'method_name',
        'method_type',
        'amount',
        'currency_code',
        'status',
        'paid_amount',
        'paid_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (OrderPayment $payment): void {
            foreach ([
                'payment_number',
                'order_id',
                'method_code',
                'method_name',
                'method_type',
                'amount',
                'currency_code',
            ] as $field) {
                if ($payment->isDirty($field)) {
                    throw new LogicException("The payment obligation {$field} snapshot is immutable.");
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:4',
            'paid_amount' => 'decimal:4',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}
