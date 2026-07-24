<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'swatch_type',
        'is_required',
        'is_configurable',
        'is_filterable',
        'is_visible_on_front',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_configurable' => 'boolean',
        'is_filterable' => 'boolean',
        'is_visible_on_front' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(AttributeTranslation::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)
            ->orderBy('sort_order');
    }

    public function productValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function productSuperAttributes(): HasMany
    {
        return $this->hasMany(ProductSuperAttribute::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_filterable_attributes'
        )->withTimestamps();
    }
}
