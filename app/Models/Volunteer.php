<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Volunteer extends Model
{
    /** @use HasFactory<\Database\Factories\VolunteerFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'user_id',
    ];

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
}
