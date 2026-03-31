<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\ShiftReservation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShiftReservation>
 */
class ShiftReservationFactory extends Factory
{
    protected $model = ShiftReservation::class;

    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'session_id' => Str::random(40),
            'expires_at' => now()->addMinutes(20),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
