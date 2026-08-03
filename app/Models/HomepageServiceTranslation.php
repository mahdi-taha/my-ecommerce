<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageServiceTranslation extends Model
{
    protected $fillable = ['locale', 'title', 'description'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(HomepageService::class, 'homepage_service_id');
    }
}
