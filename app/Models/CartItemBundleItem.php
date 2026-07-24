<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItemBundleItem extends Model
{
    protected $fillable = [
        'cart_item_id',
        'bundle_option_item_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class);
    }

    public function bundleOptionItem(): BelongsTo
    {
        return $this->belongsTo(BundleOptionItem::class);
    }
}
