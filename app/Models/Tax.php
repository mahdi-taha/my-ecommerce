<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tax extends Model
{
    protected $fillable = [
        'name',
        'rate',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'status' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
