<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductSuperAttribute extends Model
{
    protected $fillable = [
        'product_id',
        'attribute_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function options(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeOption::class,
            'product_super_attribute_options',
            'product_super_attribute_id',
            'attribute_option_id'
        )->withTimestamps();
    }
}
