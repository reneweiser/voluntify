<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Carbon\CarbonInterface;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'volunteer_job_id',
        'shift_date',
        'starts_at',
        'ends_at',
        'display_text',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Shift $shift) {
            if ($shift->starts_at !== null) {
                $shift->shift_date = $shift->starts_at->toDateString();
            }
        });
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

    public function reservations(): HasMany
    {
        return $this->hasMany(ShiftReservation::class);
    }

    public function activeReservations(): HasMany
    {
        return $this->hasMany(ShiftReservation::class)->active();
    }

    public function isFull(): bool
    {
        $signupCount = $this->active_signups_count ?? $this->activeSignups()->count();
        $reservationCount = $this->active_reservations_count ?? $this->activeReservations()->count();

        return ($signupCount + $reservationCount) >= $this->capacity;
    }

    public function spotsRemaining(): int
    {
        $signupCount = $this->active_signups_count ?? $this->activeSignups()->count();
        $reservationCount = $this->active_reservations_count ?? $this->activeReservations()->count();

        return max(0, $this->capacity - $signupCount - $reservationCount);
    }

    public function hasDefinedTimes(): bool
    {
        return $this->starts_at !== null;
    }

    public function displayTimeRange(): string
    {
        if ($this->display_text) {
            return $this->display_text;
        }

        if ($this->hasDefinedTimes()) {
            return $this->starts_at->format('H:i').' – '.$this->ends_at->format('H:i');
        }

        return $this->display_text ?? '';
    }

    public function attendanceStatusAt(CarbonInterface $scannedAt, ?int $graceMinutes = null): AttendanceStatus
    {
        if (! $this->hasDefinedTimes()) {
            return AttendanceStatus::OnTime;
        }

        $deadline = $graceMinutes !== null
            ? $this->starts_at->copy()->addMinutes($graceMinutes)
            : $this->starts_at;

        return $scannedAt->lte($deadline)
            ? AttendanceStatus::OnTime
            : AttendanceStatus::Late;
    }
}
