<?php

namespace Database\Factories;

use App\Enums\HintLocation;
use App\Models\HintText;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HintText>
 */
class HintTextFactory extends Factory
{
    protected $model = HintText::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'location' => HintLocation::SignupEmail,
            'text' => fake()->sentence(),
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(['enabled' => false]);
    }
}
