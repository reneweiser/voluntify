<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShiftSignup extends Model
{
    /** @use HasFactory<\Database\Factories\ShiftSignupFactory> */
    use HasFactory;

    protected $fillable = [
        'volunteer_id',
        'shift_id',
        'signed_up_at',
        'notification_24h_sent',
        'notification_4h_sent',
    ];

    protected function casts(): array
    {
        return [
            'signed_up_at' => 'datetime',
            'notification_24h_sent' => 'boolean',
            'notification_4h_sent' => 'boolean',
        ];
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function attendanceRecord(): HasOne
    {
        return $this->hasOne(AttendanceRecord::class);
    }
}
