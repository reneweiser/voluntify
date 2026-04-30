<?php

namespace App\Models;

use Database\Factories\EventNotificationSubscriberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventNotificationSubscriber extends Model
{
    /** @use HasFactory<EventNotificationSubscriberFactory> */
    use HasFactory;

    use MassPrunable;

    protected $fillable = [
        'event_id',
        'email',
        'verification_token_hash',
        'verification_expires_at',
        'verified_at',
        'unsubscribe_token_hash',
        'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'verification_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_notified_at' => 'datetime',
        ];
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function prunable(): Builder
    {
        return static::query()
            ->whereNull('verified_at')
            ->where('verification_expires_at', '<', now());
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scopeVerified(Builder $query): void
    {
        $query->whereNotNull('verified_at');
    }
}
