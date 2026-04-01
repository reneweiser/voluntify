<?php

namespace Database\Factories;

use App\Models\ProjectScanner;
use App\Models\ProjectScannerAssignee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectScannerAssignee> */
class ProjectScannerAssigneeFactory extends Factory
{
    protected $model = ProjectScannerAssignee::class;

    public function definition(): array
    {
        return [
            'project_scanner_id' => ProjectScanner::factory(),
            'email' => fake()->unique()->safeEmail(),
            'link_sent_at' => null,
            'authenticated_at' => null,
        ];
    }
}
