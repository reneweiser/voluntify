<?php

namespace App\Actions;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ShiftSignup;

class MarkNoShows
{
    public function execute(): int
    {
        $cutoff = now()->subHours(2);

        $signups = ShiftSignup::query()
            ->active()
            ->whereDoesntHave('attendanceRecord')
            ->whereHas('shift', fn ($q) => $q->where('ends_at', '<=', $cutoff))
            ->get();

        foreach ($signups as $signup) {
            AttendanceRecord::create([
                'shift_signup_id' => $signup->id,
                'status' => AttendanceStatus::NoShow,
                'recorded_by' => null,
                'recorded_at' => now(),
            ]);
        }

        return $signups->count();
    }
}
