<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerificationToken extends Model
{
    /** @use HasFactory<\Database\Factories\EmailVerificationTokenFactory> */
    use HasFactory;

    protected $fillable = [
        'volunteer_id',
        'event_id',
        'shift_ids',
        'gear_selections',
        'custom_field_responses',
        'token_hash',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'shift_ids' => 'array',
            'gear_selections' => 'array',
            'custom_field_responses' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
