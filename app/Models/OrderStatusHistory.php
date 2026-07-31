<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'order_status_history';

    protected $fillable = [
        'order_id',
        'type',
        'from_status',
        'to_status',
        'created_by',
        'comment',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Order status history is append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Order status history cannot be deleted directly.');
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
