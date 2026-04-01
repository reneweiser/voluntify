<?php

namespace Database\Factories;

use App\Models\GuestEntry;
use App\Models\GuestGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GuestEntry> */
class GuestEntryFactory extends Factory
{
    protected $model = GuestEntry::class;

    public function definition(): array
    {
        return [
            'guest_group_id' => GuestGroup::factory(),
            'number' => 1,
            'name' => fake()->optional(0.7)->name(),
            'email' => fake()->optional(0.6)->safeEmail(),
            'qr_token' => null,
            'checked_in_at' => null,
            'checked_in_by' => null,
        ];
    }

    public function withQrToken(): static
    {
        return $this->state(fn () => [
            'qr_token' => bin2hex(random_bytes(32)),
        ]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => [
            'checked_in_at' => now(),
        ]);
    }
}
