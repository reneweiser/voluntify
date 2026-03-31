<?php

namespace Database\Factories;

use App\Models\VolunteerGear;
use App\Models\VolunteerGearPickup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VolunteerGearPickup> */
class VolunteerGearPickupFactory extends Factory
{
    protected $model = VolunteerGearPickup::class;

    public function definition(): array
    {
        return [
            'volunteer_gear_id' => VolunteerGear::factory(),
            'picked_up_by' => null,
            'picked_up_at' => now(),
            'state' => null,
            'quantity' => 1,
        ];
    }
}
