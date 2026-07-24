<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BundleOptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bundle_option_id',
        'product_id',
        'default_quantity',
        'is_default',
        'sort_order',
        'price_override',
    ];

    protected $casts = [
        'default_quantity' => 'decimal:4',
        'is_default' => 'boolean',
        'price_override' => 'decimal:4',
    ];

    public function bundleOption(): BelongsTo
    {
        return $this->belongsTo(BundleOption::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function cartSelections(): HasMany
    {
        return $this->hasMany(CartItemBundleItem::class);
    }
}
