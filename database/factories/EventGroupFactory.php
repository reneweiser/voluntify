<?php

namespace Database\Factories;

use App\Models\EventGroup;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventGroup>
 */
class EventGroupFactory extends Factory
{
    protected $model = EventGroup::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->catchPhrase(),
            'description' => fake()->paragraph(),
        ];
    }
}
