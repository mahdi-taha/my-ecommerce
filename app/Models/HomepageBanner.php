<?php

namespace App\Models;

use App\Enums\HomepageBannerPlacement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomepageBanner extends Model
{
    protected $fillable = ['placement', 'image_path', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['placement' => HomepageBannerPlacement::class, 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(HomepageBannerTranslation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
