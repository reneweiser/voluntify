<?php

namespace Database\Factories;

use App\Models\CustomFieldResponse;
use App\Models\CustomRegistrationField;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomFieldResponse> */
class CustomFieldResponseFactory extends Factory
{
    protected $model = CustomFieldResponse::class;

    public function definition(): array
    {
        return [
            'custom_registration_field_id' => CustomRegistrationField::factory(),
            'volunteer_id' => Volunteer::factory(),
            'value' => fake()->word(),
        ];
    }
}
