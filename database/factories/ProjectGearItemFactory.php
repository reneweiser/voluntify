<?php

namespace Database\Factories;

use App\Enums\GearItemType;
use App\Models\Project;
use App\Models\ProjectGearItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectGearItem> */
class ProjectGearItemFactory extends Factory
{
    protected $model = ProjectGearItem::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->randomElement(['T-Shirt', 'Badge', 'Vest', 'Lanyard', 'Cap']),
            'type' => GearItemType::SizeSelection,
            'requires_size' => false,
            'available_sizes' => null,
            'available_states' => null,
            'sort_order' => 0,
        ];
    }

    public function sized(array $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL']): static
    {
        return $this->state(fn () => [
            'requires_size' => true,
            'available_sizes' => $sizes,
        ]);
    }

    public function quantity(int $quantityPerVolunteer = 3): static
    {
        return $this->state(fn () => [
            'type' => GearItemType::Quantity,
            'quantity_per_volunteer' => $quantityPerVolunteer,
            'requires_size' => false,
        ]);
    }
}
