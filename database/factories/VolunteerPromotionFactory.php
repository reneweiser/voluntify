<?php

namespace Database\Factories;

use App\Enums\StaffRole;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerPromotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VolunteerPromotion>
 */
class VolunteerPromotionFactory extends Factory
{
    protected $model = VolunteerPromotion::class;

    public function definition(): array
    {
        $promoter = User::factory();

        return [
            'volunteer_id' => Volunteer::factory(),
            'user_id' => User::factory(),
            'promoted_by' => $promoter,
            'role' => fake()->randomElement(StaffRole::cases()),
            'promoted_at' => now(),
        ];
    }
}
