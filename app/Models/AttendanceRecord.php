<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    /** @use HasFactory<\Database\Factories\AttendanceRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'shift_signup_id',
        'status',
        'recorded_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'recorded_at' => 'datetime',
        ];
    }

    public function shiftSignup(): BelongsTo
    {
        return $this->belongsTo(ShiftSignup::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
