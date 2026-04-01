<?php

namespace App\Models;

use App\Enums\GuestListStatus;
use Database\Factories\GuestListFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class GuestList extends Model
{
    /** @use HasFactory<GuestListFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'scanner_id',
        'name',
        'status',
        'confirmed_at',
        'gear_items',
    ];

    protected function casts(): array
    {
        return [
            'status' => GuestListStatus::class,
            'confirmed_at' => 'datetime',
            'gear_items' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(ProjectScanner::class, 'scanner_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(GuestGroup::class);
    }

    public function entries(): HasManyThrough
    {
        return $this->hasManyThrough(GuestEntry::class, GuestGroup::class);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', GuestListStatus::Confirmed);
    }

    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    public function isConfirmed(): bool
    {
        return $this->status === GuestListStatus::Confirmed;
    }

    public function isDraft(): bool
    {
        return $this->status === GuestListStatus::Draft;
    }
}
