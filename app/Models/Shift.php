<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    /** @use HasFactory<\Database\Factories\ShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'volunteer_job_id',
        'starts_at',
        'ends_at',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
        ];
    }

    public function volunteerJob(): BelongsTo
    {
        return $this->belongsTo(VolunteerJob::class);
    }

    public function signups(): HasMany
    {
        return $this->hasMany(ShiftSignup::class);
    }

    public function activeSignups(): HasMany
    {
        return $this->hasMany(ShiftSignup::class)->active();
    }

    public function isFull(): bool
    {
        $count = $this->active_signups_count ?? $this->activeSignups()->count();

        return $count >= $this->capacity;
    }

    public function spotsRemaining(): int
    {
        $count = $this->active_signups_count ?? $this->activeSignups()->count();

        return max(0, $this->capacity - $count);
    }
}
