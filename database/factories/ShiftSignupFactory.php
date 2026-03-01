<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShiftSignup>
 */
class ShiftSignupFactory extends Factory
{
    protected $model = ShiftSignup::class;

    public function definition(): array
    {
        return [
            'volunteer_id' => Volunteer::factory(),
            'shift_id' => Shift::factory(),
            'signed_up_at' => now(),
            'notification_24h_sent' => false,
            'notification_4h_sent' => false,
        ];
    }
}
