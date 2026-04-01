<?php

use App\Actions\CreateProjectScanner;
use App\Enums\ScannerMode;
use App\Enums\ScannerType;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectScanner;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->project = Project::factory()->create();
});

it('creates a scanner with correct columns', function () {
    $action = new CreateProjectScanner;

    $result = $action->execute($this->project, [
        'name' => 'Eingang Nord',
        'type' => ScannerType::EntryStaff->value,
        'modes' => [ScannerMode::Checkin->value],
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);

    expect($result)->toBeInstanceOf(ProjectScanner::class)
        ->and($result->exists)->toBeTrue()
        ->and($result->project_id)->toBe($this->project->id)
        ->and($result->name)->toBe('Eingang Nord')
        ->and($result->type)->toBe(ScannerType::EntryStaff)
        ->and($result->modes)->toBe([ScannerMode::Checkin->value]);
});

it('generates bcrypt-hashed auth_code', function () {
    $action = new CreateProjectScanner;

    $result = $action->execute($this->project, [
        'name' => 'Test Scanner',
        'type' => ScannerType::EntryStaff->value,
        'modes' => [ScannerMode::Checkin->value],
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);

    $rawCode = $result->raw_auth_code;

    expect($rawCode)->toBeString()
        ->and(strlen($rawCode))->toBe(6)
        ->and($rawCode)->toMatch('/^\d{6}$/')
        ->and(Hash::check($rawCode, $result->auth_code))->toBeTrue();
});

it('generates unique scanner_token of 64 hex chars', function () {
    $action = new CreateProjectScanner;

    $scanner1 = $action->execute($this->project, [
        'name' => 'Scanner 1',
        'type' => ScannerType::EntryStaff->value,
        'modes' => [ScannerMode::Checkin->value],
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);

    $scanner2 = $action->execute($this->project, [
        'name' => 'Scanner 2',
        'type' => ScannerType::EntryStaff->value,
        'modes' => [ScannerMode::Checkin->value],
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);

    expect(strlen($scanner1->scanner_token))->toBe(64)
        ->and($scanner1->scanner_token)->toMatch('/^[0-9a-f]{64}$/')
        ->and($scanner1->scanner_token)->not->toBe($scanner2->scanner_token);
});

it('returns raw auth code as transient virtual attribute', function () {
    $action = new CreateProjectScanner;

    $result = $action->execute($this->project, [
        'name' => 'Transient Code Test',
        'type' => ScannerType::EntryStaff->value,
        'modes' => [ScannerMode::Checkin->value],
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);

    expect($result->raw_auth_code)->toBeString();

    // Reload from DB — raw_auth_code should be gone
    $fresh = ProjectScanner::find($result->id);
    expect($fresh->raw_auth_code)->toBeNull();
});

it('accepts optional event_id, gear_item_ids, and hint_text', function () {
    $event = Event::factory()->for($this->project)->create();
    $action = new CreateProjectScanner;

    $result = $action->execute($this->project, [
        'name' => 'VA Scanner',
        'type' => ScannerType::VolunteerAdmin->value,
        'modes' => [ScannerMode::Checkin->value, ScannerMode::GearPickup->value],
        'event_id' => $event->id,
        'gear_item_ids' => [1, 2, 3],
        'hint_text' => 'Scan volunteer badges here',
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);

    expect($result->event_id)->toBe($event->id)
        ->and($result->gear_item_ids)->toBe([1, 2, 3])
        ->and($result->hint_text)->toBe('Scan volunteer badges here')
        ->and($result->type)->toBe(ScannerType::VolunteerAdmin);
});
