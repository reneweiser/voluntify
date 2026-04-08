<?php

namespace App\Models;

use App\Enums\GearItemType;
use Database\Factories\ProjectGearItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectGearItem extends Model
{
    /** @use HasFactory<ProjectGearItemFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'type',
        'quantity_per_volunteer',
        'job_ids',
        'requires_size',
        'available_sizes',
        'available_states',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => GearItemType::class,
            'quantity_per_volunteer' => 'integer',
            'job_ids' => 'array',
            'requires_size' => 'boolean',
            'available_sizes' => 'array',
            'available_states' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function volunteerGear(): HasMany
    {
        return $this->hasMany(VolunteerGear::class);
    }
}
