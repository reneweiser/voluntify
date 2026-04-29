<?php

namespace App\Models;

use App\Concerns\HasTitleImage;
use App\ValueObjects\PublicToken;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use HasTitleImage;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'timezone',
        'sender_name',
        'contact_email',
        'cancellation_enabled',
        'cancellation_cutoff_hours',
        'title_image_path',
        'website_description',
        'website_contact_info',
        'website_published',
        'attendance_states',
        'deletion_requested_at',
    ];

    protected function casts(): array
    {
        return [
            'cancellation_enabled' => 'boolean',
            'attendance_states' => 'array',
            'cancellation_cutoff_hours' => 'integer',
            'website_published' => 'boolean',
            'deletion_requested_at' => 'datetime',
        ];
    }

    /** @return array<int, array{key: string, label: string, active: bool, core: bool}> */
    public function getActiveAttendanceStatesAttribute(): array
    {
        $states = $this->attendance_states ?? self::defaultAttendanceStates();

        return array_values(array_filter($states, fn ($s) => ($s['active'] ?? false)));
    }

    /** @return array<int, array{key: string, label: string, active: bool, core: bool}> */
    public function getAllAttendanceStatesAttribute(): array
    {
        return $this->attendance_states ?? self::defaultAttendanceStates();
    }

    /** @return array<int, array{key: string, label: string, active: bool, core: bool}> */
    public static function defaultAttendanceStates(): array
    {
        return [
            ['key' => 'on_time', 'label' => 'Pünktlich', 'active' => true, 'core' => true],
            ['key' => 'late', 'label' => 'Verspätet', 'active' => true, 'core' => false],
            ['key' => 'en_route', 'label' => 'Unterwegs', 'active' => true, 'core' => false],
            ['key' => 'excused', 'label' => 'Entschuldigt', 'active' => true, 'core' => false],
            ['key' => 'no_show', 'label' => 'Nicht erschienen', 'active' => true, 'core' => true],
        ];
    }

    public function isCancellationAllowed(): bool
    {
        return $this->cancellation_enabled && $this->cancellation_cutoff_hours !== null;
    }

    public function isPendingDeletion(): bool
    {
        return $this->deletion_requested_at !== null;
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('deletion_requested_at');
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (empty($project->public_token)) {
                $project->public_token = PublicToken::generate()->value;
            }
        });

        static::deleting(function (Project $project): void {
            $project->guestLists()->delete();
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ProjectUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function publishedEvents(): HasMany
    {
        return $this->events()
            ->published()
            ->active()
            ->orderBy('starts_at');
    }

    public function volunteers(): HasMany
    {
        return $this->hasMany(Volunteer::class);
    }

    public function gearItems(): HasMany
    {
        return $this->hasMany(ProjectGearItem::class)->orderBy('sort_order');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function customRegistrationFields(): HasMany
    {
        return $this->hasMany(CustomRegistrationField::class)->orderBy('sort_order');
    }

    public function scanners(): HasMany
    {
        return $this->hasMany(ProjectScanner::class);
    }

    public function guestLists(): HasMany
    {
        return $this->hasMany(GuestList::class);
    }

    public function hintTexts(): HasMany
    {
        return $this->hasMany(HintText::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }
}
