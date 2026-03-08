<?php

namespace Database\Factories;

use App\Models\EventGearItem;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VolunteerGear> */
class VolunteerGearFactory extends Factory
{
    protected $model = VolunteerGear::class;

    public function definition(): array
    {
        return [
            'event_gear_item_id' => EventGearItem::factory(),
            'volunteer_id' => Volunteer::factory(),
            'size' => null,
            'picked_up_at' => null,
            'picked_up_by' => null,
        ];
    }

    public function pickedUp(): static
    {
        return $this->state(fn () => [
            'picked_up_at' => now(),
            'picked_up_by' => \App\Models\User::factory(),
        ]);
    }
}
