<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DatabaseNotification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'audience_code',
        'user_id',
        'event_code',
        'entity_type',
        'entity_id',
        'title',
        'body',
        'payload',
        'read_at',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (DatabaseNotification $notification): void {
            if (collect($notification->getDirty())->except('read_at')->isNotEmpty()) {
                throw new LogicException('Database notification content is immutable.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Database notifications cannot be deleted directly.');
        });
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
