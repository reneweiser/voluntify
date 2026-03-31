<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Volunteer>
 */
class VolunteerFactory extends Factory
{
    protected $model = Volunteer::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'email_verified_at' => null,
            'project_id' => Project::factory(),
            'user_id' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => now(),
        ]);
    }

    public function promoted(): static
    {
        return $this->has(
            VolunteerPromotionFactory::new(),
            'promotion'
        );
    }
}
