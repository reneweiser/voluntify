<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'ai_api_key' => null,
        ];
    }

    public function withAiKey(): static
    {
        return $this->state(fn (array $attributes) => [
            'ai_api_key' => 'sk-test-'.Str::random(40),
        ]);
    }
}
