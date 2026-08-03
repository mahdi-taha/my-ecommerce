<?php

namespace App\Models;

use App\Enums\HomepageServiceIcon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomepageService extends Model
{
    protected $fillable = ['icon', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'icon' => HomepageServiceIcon::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(HomepageServiceTranslation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
