<?php

namespace App\Models;

use App\Enums\ActivityCategory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'event_id',
        'causer_type',
        'causer_id',
        'subject_type',
        'subject_id',
        'action',
        'category',
        'description',
        'properties',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => ActivityCategory::class,
            'properties' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForOrganization(Builder $query, int $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }

    public function scopeForEvent(Builder $query, int $eventId): void
    {
        $query->where('event_id', $eventId);
    }

    public function scopeForCategory(Builder $query, ActivityCategory $category): void
    {
        $query->where('category', $category);
    }

    public function scopeForCauser(Builder $query, Model $causer): void
    {
        $query->where('causer_type', $causer::class)
            ->where('causer_id', $causer->id);
    }

    public function scopeInDateRange(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereBetween('created_at', [$from, $to]);
    }
}
