<?php

namespace App\Models;

use App\Enums\HintLocation;
use Database\Factories\HintTextFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HintText extends Model
{
    /** @use HasFactory<HintTextFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'location',
        'text',
        'enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location' => HintLocation::class,
            'enabled' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeForLocation(Builder $query, HintLocation $location): Builder
    {
        return $query->where('location', $location);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }
}
