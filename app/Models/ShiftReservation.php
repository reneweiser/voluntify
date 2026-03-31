<?php

namespace App\Models;

use Database\Factories\ShiftReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A temporary hold on a shift slot during the multi-step signup wizard.
 *
 * Reservations are scoped by session_id (Laravel session ID), not by volunteer,
 * because volunteer identity is unknown at step 1 (shift selection). The volunteer
 * provides their personal information in step 3, after shifts have already been reserved.
 *
 * Reservations expire after a configurable TTL (default 20 minutes). Expired reservations
 * are cleaned up by the `app:release-expired-reservations` scheduled command.
 */
class ShiftReservation extends Model
{
    /** @use HasFactory<ShiftReservationFactory> */
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'session_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    public function scopeForSession(Builder $query, string $sessionId): void
    {
        $query->where('session_id', $sessionId);
    }

    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }
}
