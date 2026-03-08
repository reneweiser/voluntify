<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventGearItem extends Model
{
    /** @use HasFactory<\Database\Factories\EventGearItemFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'requires_size',
        'available_sizes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_size' => 'boolean',
            'available_sizes' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function volunteerGear(): HasMany
    {
        return $this->hasMany(VolunteerGear::class);
    }
}
