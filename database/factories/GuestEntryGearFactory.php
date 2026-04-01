<?php

namespace Database\Factories;

use App\Models\GuestEntry;
use App\Models\GuestEntryGear;
use App\Models\ProjectGearItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GuestEntryGear> */
class GuestEntryGearFactory extends Factory
{
    protected $model = GuestEntryGear::class;

    public function definition(): array
    {
        return [
            'guest_entry_id' => GuestEntry::factory(),
            'project_gear_item_id' => ProjectGearItem::factory(),
            'quantity' => 1,
            'picked_up_count' => 0,
            'selection' => null,
            'status' => null,
        ];
    }
}
