<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'created_by' => User::factory(),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'sent_at' => now(),
            'recipient_count' => fake()->numberBetween(1, 50),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'send_at' => now()->addHours(2),
        ]);
    }
}
