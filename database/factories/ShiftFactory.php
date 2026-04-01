<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\VolunteerJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 week', '+3 months');
        $endsAt = (clone $startsAt)->modify('+'.fake()->numberBetween(2, 6).' hours');

        return [
            'volunteer_job_id' => VolunteerJob::factory(),
            'shift_date' => $startsAt->format('Y-m-d'),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => fake()->numberBetween(5, 30),
        ];
    }

    public function withoutTimes(?string $displayText = null): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => null,
            'ends_at' => null,
            'display_text' => $displayText,
        ]);
    }

    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity' => 0,
        ]);
    }
}
