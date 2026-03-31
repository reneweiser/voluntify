<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $name = fake()->unique()->catchPhrase();
        $startsAt = fake()->dateTimeBetween('+1 week', '+3 months');
        $endsAt = (clone $startsAt)->modify('+'.fake()->numberBetween(2, 8).' hours');

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'location' => fake()->address(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => EventStatus::Draft,
            'visibility' => EventVisibility::Public,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Event $event) {
            if (! $event->project_id) {
                $org = $event->organization_id
                    ? Organization::find($event->organization_id)
                    : Organization::factory()->create();

                if (! $event->organization_id) {
                    $event->organization_id = $org->id;
                }

                $event->project_id = Project::factory()->for($org)->create()->id;
            }
        });
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::PublishedOpen,
        ]);
    }

    public function publishedClosed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::PublishedClosed,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Archived,
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => EventVisibility::Private,
        ]);
    }
}
