<?php

namespace App\Actions;

use App\Enums\AttendanceStatus;
use App\Events\Activity\AttendanceRecorded;
use App\Models\AttendanceRecord;
use App\Models\EventArrival;
use App\Models\ShiftSignup;
use App\Models\User;

class RecordAttendance
{
    public function execute(ShiftSignup $signup, AttendanceStatus $status, ?User $recordedBy = null): AttendanceRecord
    {
        $record = AttendanceRecord::updateOrCreate(
            ['shift_signup_id' => $signup->id],
            [
                'status' => $status,
                'recorded_by' => $recordedBy?->id,
                'recorded_at' => now(),
            ],
        );

        AttendanceRecorded::dispatch($record, $signup, $recordedBy);

        $record->conflictDetected = false;

        if ($status === AttendanceStatus::NoShow) {
            $eventId = $signup->shift->volunteerJob->event_id;
            $hasArrival = EventArrival::where('volunteer_id', $signup->volunteer_id)
                ->where('event_id', $eventId)
                ->exists();

            $record->conflictDetected = $hasArrival;
        }

        return $record;
    }
}
