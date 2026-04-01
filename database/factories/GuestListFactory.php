<?php

namespace Database\Factories;

use App\Enums\GuestListStatus;
use App\Models\GuestList;
use App\Models\Project;
use App\Models\ProjectScanner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GuestList> */
class GuestListFactory extends Factory
{
    protected $model = GuestList::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'scanner_id' => ProjectScanner::factory(),
            'name' => fake()->words(3, true),
            'status' => GuestListStatus::Draft,
            'confirmed_at' => null,
            'gear_items' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => GuestListStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }
}
