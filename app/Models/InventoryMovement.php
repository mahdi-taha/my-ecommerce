<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryMovement extends Model
{
    public const TYPE_OPENING = 'opening';

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_STOCK_COUNT = 'stock_count';

    public const TYPE_SALE = 'sale';

    public const TYPE_RETURN = 'return';

    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'quantity_before' => 'decimal:4',
        'quantity_after' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function types(): array
    {
        return [
            self::TYPE_OPENING,
            self::TYPE_RECEIPT,
            self::TYPE_ADJUSTMENT,
            self::TYPE_STOCK_COUNT,
            self::TYPE_SALE,
            self::TYPE_RETURN,
        ];
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
