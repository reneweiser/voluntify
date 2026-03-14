<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class Volunteer extends Model
{
    /** @use HasFactory<\Database\Factories\VolunteerFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'email_verified_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function markEmailAsVerified(): void
    {
        $this->update(['email_verified_at' => now()]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shiftSignups(): HasMany
    {
        return $this->hasMany(ShiftSignup::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function eventArrivals(): HasMany
    {
        return $this->hasMany(EventArrival::class);
    }

    public function magicLinkTokens(): HasMany
    {
        return $this->hasMany(MagicLinkToken::class);
    }

    public function promotion(): HasOne
    {
        return $this->hasOne(VolunteerPromotion::class);
    }

    public function volunteerGear(): HasMany
    {
        return $this->hasMany(VolunteerGear::class);
    }

    public function customFieldResponses(): HasMany
    {
        return $this->hasMany(CustomFieldResponse::class);
    }

    public function scopeWithCustomFields(Builder $query, int $eventId): void
    {
        $query->with(['customFieldResponses' => function ($q) use ($eventId) {
            $q->whereHas('field', fn ($fq) => $fq->withTrashed()->where('event_id', $eventId))
                ->with(['field' => fn ($fq) => $fq->withTrashed()]);
        }]);
    }

    public function scopeForEvent(Builder $query, int $eventId): void
    {
        $query->whereHas('tickets', fn (Builder $q) => $q->where('event_id', $eventId));
    }

    public function scopeSearch(Builder $query, string $search): void
    {
        $useLike = mb_strlen($search) < 3 || $query->getConnection()->getDriverName() !== 'mysql';

        if ($useLike) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'LIKE', '%'.$search.'%')
                    ->orWhere('email', 'LIKE', '%'.$search.'%');
            });

            return;
        }

        $term = str_replace(['+', '-', '*', '~', '<', '>', '(', ')', '"'], '', $search);
        $booleanTerm = '+'.implode('* +', explode(' ', trim($term))).'*';

        $query->whereRaw(
            'MATCH(name, email) AGAINST(? IN BOOLEAN MODE)',
            [$booleanTerm],
        );
    }
}
