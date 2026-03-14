<?php

namespace App\Events\Activity;

use App\Models\AttendanceRecord;
use App\Models\ShiftSignup;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class AttendanceRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly AttendanceRecord $record,
        public readonly ShiftSignup $signup,
        public readonly ?User $causer,
    ) {}
}
