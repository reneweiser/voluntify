<?php

namespace Database\Factories;

use App\Models\AnnouncementTemplate;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnouncementTemplate>
 */
class AnnouncementTemplateFactory extends Factory
{
    protected $model = AnnouncementTemplate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
        ];
    }
}
