<?php

namespace App\Models;

use App\Enums\ScannerType;
use Database\Factories\ProjectScannerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectScanner extends Model
{
    /** @use HasFactory<ProjectScannerFactory> */
    use HasFactory;

    public const CONTRACT_VERSION = 1;

    protected $fillable = [
        'project_id',
        'entry_event_id',
        'pool_event_ids',
        'requires_configuration_review',
        'name',
        'type',
        'modes',
        'gear_item_ids',
        'hint_text',
        'starts_at',
        'ends_at',
        'auth_code',
        'scanner_token',
    ];

    protected $hidden = [
        'scanner_token',
    ];

    protected function casts(): array
    {
        return [
            'type' => ScannerType::class,
            'modes' => 'array',
            'gear_item_ids' => 'array',
            'pool_event_ids' => 'array',
            'requires_configuration_review' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function entryEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'entry_event_id');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(ProjectScannerAssignee::class);
    }

    public function guestLists(): HasMany
    {
        return $this->hasMany(GuestList::class, 'scanner_id');
    }

    /**
     * Computed status based on time window.
     */
    public function getStatusAttribute(): string
    {
        if (now()->lt($this->starts_at)) {
            return 'scheduled';
        }

        if (now()->gt($this->ends_at)) {
            return 'expired';
        }

        return 'active';
    }

    public function isActive(): bool
    {
        return now()->between($this->starts_at, $this->ends_at);
    }

    public function isExpired(): bool
    {
        return now()->gt($this->ends_at);
    }

    public function isScheduled(): bool
    {
        return now()->lt($this->starts_at);
    }

    /**
     * Check if this scanner has the given mode enabled.
     */
    public function hasMode(string $mode): bool
    {
        return in_array($mode, $this->modes ?? [], true);
    }

    /** @return array<int, int> */
    public function configuredPoolEventIds(): array
    {
        return array_values(array_map('intval', $this->pool_event_ids ?? []));
    }

    public function includesEvent(int $eventId): bool
    {
        return in_array($eventId, $this->configuredPoolEventIds(), true);
    }

    /**
     * Scope: only active scanners (within time window).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    /**
     * Scope: scanners not yet started.
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('starts_at', '>', now());
    }

    /**
     * Scope: scanners whose window opens within the given number of minutes.
     */
    public function scopeWindowOpensSoon(Builder $query, int $minutes): Builder
    {
        return $query->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addMinutes($minutes));
    }
}
