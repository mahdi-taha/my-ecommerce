<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleOptionTranslation extends Model
{
    protected $fillable = [
        'bundle_option_id',
        'locale',
        'title',
    ];

    public function bundleOption(): BelongsTo
    {
        return $this->belongsTo(BundleOption::class);
    }
}
