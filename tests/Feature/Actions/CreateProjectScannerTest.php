<?php

use App\Actions\CreateProjectScanner;
use App\Enums\ScannerMode;
use App\Enums\ScannerType;
use App\Models\Event;
use App\Models\GuestGroup;
use App\Models\GuestList;
use App\Models\Project;
use App\Models\ProjectScanner;

beforeEach(function () {
    $this->project = Project::factory()->create();
});

it('creates a scanner with correct columns', function () {
    $event = Event::factory()->for($this->project)->create();
    $action = new CreateProjectScanner;

    $result = $action->execute($this->project, [
        'name' => 'Eingang Nord',
        'type' => ScannerType::EntryStaff->value,
        'modes' => [ScannerMode::Checkin->value],
        'entry_event_id' => $event->id,
        'pool_event_ids' => [$event->id],
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

it('stores plaintext 6-digit auth code', function () {
    $event = Event::factory()->for($this->project)->create();
    $action = new CreateProjectScanner;

    $result = $action->execute($this->project, [
        'name' => 'Test Scanner',
        'type' => ScannerType::EntryStaff->value,
        'modes' => [ScannerMode::Checkin->value],
        'entry_event_id' => $event->id,
        'pool_event_ids' => [$event->id],
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);

    expect($result->auth_code)->toBeString()
        ->and(strlen($result->auth_code))->toBe(6)
        ->and($result->auth_code)->toMatch('/^\d{6}$/');

    // Reload from DB — plaintext code should be readable
    $fresh = ProjectScanner::find($result->id);
    expect($fresh->auth_code)->toBe($result->auth_code);
});

it('generates unique scanner_token of 64 hex chars', function () {
    $event = Event::factory()->for($this->project)->create();
    $action = new CreateProjectScanner;

    $scanner1 = $action->execute($this->project, [
        'name' => 'Scanner 1',
        'type' => ScannerType::EntryStaff->value,
        'modes' => [ScannerMode::Checkin->value],
        'entry_event_id' => $event->id,
        'pool_event_ids' => [$event->id],
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);

    $scanner2 = $action->execute($this->project, [
        'name' => 'Scanner 2',
        'type' => ScannerType::EntryStaff->value,
        'modes' => [ScannerMode::Checkin->value],
        'entry_event_id' => $event->id,
        'pool_event_ids' => [$event->id],
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);

    expect(strlen($scanner1->scanner_token))->toBe(64)
        ->and($scanner1->scanner_token)->toMatch('/^[0-9a-f]{64}$/')
        ->and($scanner1->scanner_token)->not->toBe($scanner2->scanner_token);
});

it('does not set transient raw_auth_code property', function () {
    $event = Event::factory()->for($this->project)->create();
    $action = new CreateProjectScanner;

    $result = $action->execute($this->project, [
        'name' => 'No Transient Test',
        'type' => ScannerType::EntryStaff->value,
        'modes' => [ScannerMode::Checkin->value],
        'entry_event_id' => $event->id,
        'pool_event_ids' => [$event->id],
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);

    expect($result->raw_auth_code ?? null)->toBeNull();
});

it('stores entry and pool events', function () {
    $entryEvent = Event::factory()->for($this->project)->create();
    $poolEvent = Event::factory()->for($this->project)->create();
    $action = new CreateProjectScanner;

    $result = $action->execute($this->project, [
        'name' => 'VA Scanner',
        'type' => ScannerType::VolunteerAdmin->value,
        'modes' => [ScannerMode::Checkin->value, ScannerMode::GearPickup->value],
        'entry_event_id' => $entryEvent->id,
        'pool_event_ids' => [$entryEvent->id, $poolEvent->id],
        'gear_item_ids' => [1, 2, 3],
        'hint_text' => 'Scan volunteer badges here',
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);

    expect($result->entry_event_id)->toBe($entryEvent->id)
        ->and($result->pool_event_ids)->toBe([$entryEvent->id, $poolEvent->id])
        ->and($result->gear_item_ids)->toBe([1, 2, 3])
        ->and($result->hint_text)->toBe('Scan volunteer badges here')
        ->and($result->type)->toBe(ScannerType::VolunteerAdmin);
});

it('stores optional guest group ids for gear scanners', function () {
    $entryEvent = Event::factory()->for($this->project)->create();
    $guestList = GuestList::factory()->confirmed()->create(['project_id' => $this->project->id]);
    $group = GuestGroup::factory()->for($guestList)->create();
    $action = new CreateProjectScanner;

    $result = $action->execute($this->project, [
        'name' => 'Gear Scanner',
        'type' => ScannerType::Gear->value,
        'modes' => [ScannerMode::GearPickup->value],
        'entry_event_id' => $entryEvent->id,
        'pool_event_ids' => [$entryEvent->id],
        'guest_group_ids' => [$group->id],
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);

    expect($result->type)->toBe(ScannerType::Gear)
        ->and($result->guest_group_ids)->toBe([$group->id]);
});
