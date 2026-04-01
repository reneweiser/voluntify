<?php

namespace App\Models;

use App\Concerns\HasTitleImage;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\ValueObjects\PublicToken;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use HasTitleImage;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'status',
        'title_image_path',
        'attendance_grace_minutes',
        'phone_required',
        'visibility',
        'notification_email',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => EventStatus::class,
            'attendance_grace_minutes' => 'integer',
            'phone_required' => 'boolean',
            'visibility' => EventVisibility::class,
            'volunteer_count' => 'integer',
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

    public function eventArrivals(): HasMany
    {
        return $this->hasMany(EventArrival::class);
    }

    public function emailTemplates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(EventAnnouncement::class);
    }

    public function customRegistrationFields(): HasMany
    {
        return $this->hasMany(CustomRegistrationField::class)->orderBy('sort_order');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('visibility', EventVisibility::Public);
    }

    public function scopePublished(Builder $query): void
    {
        $query->whereIn('status', [EventStatus::PublishedOpen, EventStatus::PublishedClosed]);
    }

    public function scopePublishedByToken(Builder $query, string $publicToken): void
    {
        $query->where('public_token', $publicToken)->published();
    }

    public function scopeWithVolunteerCount(Builder $query): void
    {
        $query->addSelect(['volunteer_count' => Volunteer::query()
            ->selectRaw('count(*)')
            ->whereColumn('volunteers.project_id', 'events.project_id')
            ->whereHas('shiftSignups', fn ($q) => $q->whereHas('shift.volunteerJob', fn ($sq) => $sq->whereColumn('event_id', 'events.id'))
            ),
        ]);
    }
}
