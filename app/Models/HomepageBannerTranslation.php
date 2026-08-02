<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageBannerTranslation extends Model
{
    protected $fillable = ['locale', 'eyebrow', 'title', 'body', 'button_label', 'link_url', 'image_alt'];

    public function banner(): BelongsTo
    {
        return $this->belongsTo(HomepageBanner::class, 'homepage_banner_id');
    }
}
