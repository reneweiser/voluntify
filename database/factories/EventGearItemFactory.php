<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventGearItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventGearItem> */
class EventGearItemFactory extends Factory
{
    protected $model = EventGearItem::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['T-Shirt', 'Badge', 'Vest', 'Lanyard', 'Cap']),
            'requires_size' => false,
            'available_sizes' => null,
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
}
