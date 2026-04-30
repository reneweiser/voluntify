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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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
        'priority_unlock_threshold_percent',
        'priority_gate_unlocked_at',
        'was_previously_published',
        'deletion_requested_at',
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
            'priority_unlock_threshold_percent' => 'integer',
            'priority_gate_unlocked_at' => 'datetime',
            'was_previously_published' => 'boolean',
            'deletion_requested_at' => 'datetime',
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

    public function shifts(): HasManyThrough
    {
        return $this->hasManyThrough(Shift::class, VolunteerJob::class, 'event_id', 'volunteer_job_id');
    }

    public function emailTemplates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
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

    public function isPendingDeletion(): bool
    {
        return $this->deletion_requested_at !== null;
    }

    public function hasActivePriorityShifts(): bool
    {
        return $this->shifts()
            ->where('shifts.is_active', true)
            ->where('shifts.is_priority', true)
            ->exists();
    }

    public function priorityCapacityTotal(): int
    {
        return (int) $this->shifts()
            ->where('shifts.is_active', true)
            ->where('shifts.is_priority', true)
            ->sum('shifts.capacity');
    }

    public function priorityFilledSpots(): int
    {
        return ShiftSignup::query()
            ->active()
            ->whereHas('shift', fn (Builder $query) => $query
                ->where('shifts.is_active', true)
                ->where('shifts.is_priority', true)
                ->whereHas('volunteerJob', fn (Builder $jobQuery) => $jobQuery->where('event_id', $this->id)))
            ->count();
    }

    public function priorityFillRate(): float
    {
        $capacityTotal = $this->priorityCapacityTotal();

        if ($capacityTotal === 0) {
            return 0.0;
        }

        return $this->priorityFilledSpots() / $capacityTotal;
    }

    public function isPriorityGateOpen(): bool
    {
        return $this->priority_unlock_threshold_percent === null
            || $this->priority_unlock_threshold_percent <= 0
            || $this->priority_gate_unlocked_at !== null
            || ! $this->hasActivePriorityShifts();
    }

    public function evaluatePriorityGate(): void
    {
        if ($this->isPriorityGateOpen()) {
            return;
        }

        if (($this->priorityFillRate() * 100) < $this->priority_unlock_threshold_percent) {
            return;
        }

        $this->forceFill([
            'priority_gate_unlocked_at' => now(),
        ])->save();
    }

    public function priorityGateMessage(): string
    {
        return __('Please fill the priority shifts first. Other shifts unlock once :percent% of the priority slots are filled.', [
            'percent' => $this->priority_unlock_threshold_percent,
        ]);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('deletion_requested_at');
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
