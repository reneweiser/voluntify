<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\VolunteerJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolunteerJob>
 */
class VolunteerJobFactory extends Factory
{
    protected $model = VolunteerJob::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->jobTitle(),
            'description' => fake()->sentence(),
            'instructions' => fake()->paragraph(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
