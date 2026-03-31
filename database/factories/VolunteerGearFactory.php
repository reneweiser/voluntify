<?php

namespace Database\Factories;

use App\Models\ProjectGearItem;
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
            'project_gear_item_id' => ProjectGearItem::factory(),
            'volunteer_id' => Volunteer::factory(),
            'size' => null,
        ];
    }
}
