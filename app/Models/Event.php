<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\ValueObjects\PublicToken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => EventStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (empty($event->public_token)) {
                $event->public_token = PublicToken::generate()->value;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function volunteerJobs(): HasMany
    {
        return $this->hasMany(VolunteerJob::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function eventArrivals(): HasMany
    {
        return $this->hasMany(EventArrival::class);
    }

    public function volunteers(): HasManyThrough
    {
        return $this->hasManyThrough(Volunteer::class, Ticket::class, 'event_id', 'id', 'id', 'volunteer_id');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', EventStatus::Published);
    }
}
