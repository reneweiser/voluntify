<?php

namespace Database\Factories;

use App\Enums\ScannerMode;
use App\Enums\ScannerType;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectScanner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectScanner> */
class ProjectScannerFactory extends Factory
{
    protected $model = ProjectScanner::class;

    public function configure(): static
    {
        return $this->afterMaking(function (ProjectScanner $scanner): void {
            if ($scanner->entry_event_id !== null && is_array($scanner->pool_event_ids) && $scanner->pool_event_ids !== []) {
                return;
            }

            $project = Project::query()->findOrFail($scanner->project_id);
            $event = Event::factory()->for($project)->create();

            $scanner->entry_event_id = $scanner->entry_event_id ?? $event->id;
            $scanner->pool_event_ids = $scanner->pool_event_ids ?? [$scanner->entry_event_id];
        });
    }

    public function definition(): array
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return [
            'project_id' => Project::factory(),
            'entry_event_id' => null,
            'pool_event_ids' => null,
            'requires_configuration_review' => false,
            'name' => fake()->words(2, true),
            'type' => ScannerType::EntryStaff,
            'modes' => [ScannerMode::Checkin->value],
            'gear_item_ids' => null,
            'hint_text' => null,
            'starts_at' => now(),
            'ends_at' => now()->addHours(2),
            'auth_code' => $code,
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

    public function forEntryEvent(Event $event): static
    {
        return $this->state(fn () => [
            'project_id' => $event->project_id,
            'entry_event_id' => $event->id,
            'pool_event_ids' => [$event->id],
        ]);
    }

    /** @param  array<int, int>  $poolEventIds */
    public function withPoolEvents(Event $entryEvent, array $poolEventIds): static
    {
        return $this->state(fn () => [
            'project_id' => $entryEvent->project_id,
            'entry_event_id' => $entryEvent->id,
            'pool_event_ids' => array_values(array_unique(array_map('intval', $poolEventIds))),
        ]);
    }

    public function requiresConfigurationReview(): static
    {
        return $this->state(fn () => [
            'requires_configuration_review' => true,
        ]);
    }

    /**
     * Create a scanner with a known plaintext auth code.
     */
    public function withAuthCode(string $plainCode): static
    {
        return $this->state(fn () => [
            'auth_code' => $plainCode,
        ]);
    }
}
