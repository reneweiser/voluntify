<?php

namespace App\Models;

use App\Concerns\HasTitleImage;
use App\Enums\EventStatus;
use App\ValueObjects\PublicToken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    use HasTitleImage;

    protected $fillable = [
        'organization_id',
        'event_group_id',
        'name',
        'slug',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'status',
        'title_image_path',
        'cancellation_cutoff_hours',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => EventStatus::class,
            'cancellation_cutoff_hours' => 'integer',
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

    public function emailTemplates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(EventAnnouncement::class);
    }

    public function gearItems(): HasMany
    {
        return $this->hasMany(EventGearItem::class)->orderBy('sort_order');
    }

    public function eventGroup(): BelongsTo
    {
        return $this->belongsTo(EventGroup::class);
    }

    public function isCancellationAllowed(): bool
    {
        return $this->cancellation_cutoff_hours !== null;
    }

    public static function generateUniqueSlug(Organization $organization, string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $suffix = 2;

        while ($organization->events()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', EventStatus::Published);
    }

    public function scopePublishedByToken(Builder $query, string $publicToken): void
    {
        $query->where('public_token', $publicToken)->published();
    }
}
