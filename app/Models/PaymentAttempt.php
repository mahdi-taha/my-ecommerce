<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_payment_id',
        'attempt_number',
        'provider',
        'status',
        'amount',
        'currency_code',
        'transaction_reference',
        'customer_note',
        'provider_transaction_id',
        'failure_code',
        'failure_message',
        'metadata_json',
        'initiated_at',
        'completed_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (PaymentAttempt $attempt): void {
            $originalStatus = PaymentAttemptStatus::from($attempt->getRawOriginal('status'));

            if ($originalStatus->isTerminal()) {
                throw new LogicException('Terminal payment attempts are immutable.');
            }

            foreach (['order_payment_id', 'attempt_number', 'amount', 'currency_code'] as $field) {
                if ($attempt->isDirty($field)) {
                    throw new LogicException("The payment attempt {$field} is immutable.");
                }
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Payment attempts are append-only and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'amount' => 'decimal:4',
            'metadata_json' => 'array',
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function orderPayment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class);
    }
}
