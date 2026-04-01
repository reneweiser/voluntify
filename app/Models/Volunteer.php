<?php

namespace App\Models;

use Database\Factories\VolunteerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class Volunteer extends Model
{
    /** @use HasFactory<VolunteerFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'email_verified_at',
        'project_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->first_name.' '.$this->last_name,
        );
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    public function scopeWithCustomFields(Builder $query, int $eventId, ?int $projectId = null): void
    {
        $query->with(['customFieldResponses' => function ($q) use ($eventId, $projectId) {
            $q->whereHas('field', fn ($fq) => $fq->withTrashed()->where(function ($sq) use ($eventId, $projectId) {
                $sq->where('event_id', $eventId);
                if ($projectId !== null) {
                    $sq->orWhere('project_id', $projectId);
                }
            }))
                ->with(['field' => fn ($fq) => $fq->withTrashed()]);
        }]);
    }

    public function scopeForProject(Builder $query, int $projectId): void
    {
        $query->where('project_id', $projectId);
    }

    public function scopeForEvent(Builder $query, int $eventId): void
    {
        $query->where(function (Builder $q) use ($eventId) {
            $q->whereHas('shiftSignups', fn (Builder $sq) => $sq->whereHas('shift.volunteerJob', fn (Builder $jq) => $jq->where('event_id', $eventId))
            )->orWhereHas('eventArrivals', fn (Builder $eq) => $eq->where('event_id', $eventId));
        });
    }

    public function scopeSearch(Builder $query, string $search): void
    {
        $useLike = mb_strlen($search) < 3 || $query->getConnection()->getDriverName() !== 'mysql';

        if ($useLike) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('first_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('email', 'LIKE', '%'.$search.'%');
            });

            return;
        }

        $term = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $search);
        $words = array_filter(explode(' ', trim($term)));

        if ($words === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $booleanTerm = '+'.implode('* +', $words).'*';

        $query->whereRaw(
            'MATCH(first_name, last_name, email) AGAINST(? IN BOOLEAN MODE)',
            [$booleanTerm],
        );
    }
}
