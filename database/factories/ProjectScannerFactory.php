<?php

namespace Database\Factories;

use App\Enums\ScannerMode;
use App\Enums\ScannerType;
use App\Models\Project;
use App\Models\ProjectScanner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<ProjectScanner> */
class ProjectScannerFactory extends Factory
{
    protected $model = ProjectScanner::class;

    public function definition(): array
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return [
            'project_id' => Project::factory(),
            'event_id' => null,
            'name' => fake()->words(2, true),
            'type' => ScannerType::EntryStaff,
            'modes' => [ScannerMode::Checkin->value],
            'gear_item_ids' => null,
            'hint_text' => null,
            'starts_at' => now(),
            'ends_at' => now()->addHours(2),
            'auth_code' => Hash::make($code),
            'scanner_token' => bin2hex(random_bytes(32)),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subHours(4),
            'ends_at' => now()->subHours(2),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(4),
        ]);
    }

    public function volunteerAdmin(): static
    {
        return $this->state(fn () => [
            'type' => ScannerType::VolunteerAdmin,
            'modes' => [ScannerMode::Checkin->value, ScannerMode::GearPickup->value],
        ]);
    }

    /**
     * Create a scanner with a known plaintext auth code.
     */
    public function withAuthCode(string $plainCode): static
    {
        return $this->state(fn () => [
            'auth_code' => Hash::make($plainCode),
        ]);
    }
}
