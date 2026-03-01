<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
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
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Published,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Archived,
        ]);
    }
}
