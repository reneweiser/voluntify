<?php

namespace Database\Factories;

use App\Enums\ActivityCategory;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = Organization::factory()->create();
        $event = Event::factory()->for($organization)->create();

        return [
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'subject_type' => Event::class,
            'subject_id' => $event->id,
            'action' => fake()->word(),
            'category' => fake()->randomElement(ActivityCategory::cases()),
            'description' => fake()->sentence(),
        ];
    }

    public function forEvent(Event $event): static
    {
        return $this->state(fn () => [
            'event_id' => $event->id,
            'organization_id' => $event->organization_id,
        ]);
    }

    public function causedBy(User $user): static
    {
        return $this->state(fn () => [
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);
    }
}
