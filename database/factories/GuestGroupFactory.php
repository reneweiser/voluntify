<?php

namespace Database\Factories;

use App\Models\GuestGroup;
use App\Models\GuestList;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GuestGroup> */
class GuestGroupFactory extends Factory
{
    protected $model = GuestGroup::class;

    public function definition(): array
    {
        return [
            'guest_list_id' => GuestList::factory(),
            'label' => fake()->name(),
            'guest_count' => fake()->numberBetween(1, 5),
        ];
    }
}
