<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ShiftSignup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        return [
            'shift_signup_id' => ShiftSignup::factory(),
            'status' => fake()->randomElement(AttendanceStatus::cases()),
            'recorded_by' => User::factory(),
            'recorded_at' => now(),
        ];
    }
}
