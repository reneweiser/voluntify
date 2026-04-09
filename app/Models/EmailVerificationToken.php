<?php

namespace App\Models;

use Database\Factories\EmailVerificationTokenFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerificationToken extends Model
{
    /** @use HasFactory<EmailVerificationTokenFactory> */
    use HasFactory;

    use MassPrunable;

    protected $fillable = [
        'volunteer_id',
        'event_id',
        'project_id',
        'email',
        'shift_ids',
        'gear_selections',
        'custom_field_responses',
        'token_hash',
        'expires_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'shift_ids' => 'array',
            'gear_selections' => 'array',
            'custom_field_responses' => 'array',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function prunable(): Builder
    {
        return static::where(function (Builder $query) {
            $query->whereNotNull('verified_at')
                ->where('verified_at', '<', now()->subDays(7));
        })->orWhere(function (Builder $query) {
            $query->whereNull('verified_at')
                ->where('expires_at', '<', now());
        });
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
