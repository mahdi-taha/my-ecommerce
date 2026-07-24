<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BundleOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'type',
        'is_required',
        'sort_order',
        'min_selections',
        'max_selections',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(BundleOptionTranslation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleOptionItem::class)
            ->orderBy('sort_order');
    }
}
