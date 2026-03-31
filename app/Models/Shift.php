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

    public function attendanceStatusAt(CarbonInterface $scannedAt, ?int $graceMinutes = null): AttendanceStatus
    {
        $deadline = $graceMinutes !== null
            ? $this->starts_at->copy()->addMinutes($graceMinutes)
            : $this->starts_at;

        return $scannedAt->lte($deadline)
            ? AttendanceStatus::OnTime
            : AttendanceStatus::Late;
    }
}
